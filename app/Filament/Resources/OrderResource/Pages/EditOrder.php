<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Services\InventoryReservationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Filament\Actions\Action;

use Illuminate\Support\Facades\Mail;
use App\Mail\OrderShippedMail;

use App\Models\Order;


class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [

            Action::make('download_invoice')
                ->label('Invoice PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn () => route('admin.orders.invoice', ['orderNumber' => $this->record->order_number]))
                ->openUrlInNewTab(),

            Action::make('shipping_label')
                ->label('Shipping Label')
                ->icon('heroicon-o-truck')
                ->url(fn () => route('admin.orders.shippingLabel', ['orderNumber' => $this->record->order_number]))
                ->openUrlInNewTab(),

            

            // Pending -> Confirmed
            Actions\Action::make('mark_confirmed')
                ->label('Mark Confirmed')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === Order::STATUS_PENDING)
                ->action(function () {
                    $this->record->update(['status' => Order::STATUS_CONFIRMED]);

                    Notification::make()->title('Order marked as Confirmed.')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            // Confirmed -> Ready to Pickup (pickup orders only)
            Actions\Action::make('mark_ready_to_pickup')
                ->label('Ready to Pickup')
                ->icon('heroicon-o-building-storefront')
                ->requiresConfirmation()
                ->visible(fn () =>
                    $this->record->status === Order::STATUS_CONFIRMED
                    && $this->record->fulfillment_method === 'pickup'
                )
                ->action(function () {
                    $this->record->update(['status' => Order::STATUS_READY_TO_PICKUP]);

                    Notification::make()->title('Order marked as Ready to Pickup.')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            // Confirmed -> On Delivery (delivery orders only)
            Actions\Action::make('mark_on_delivery')
                ->label('On Delivery')
                ->icon('heroicon-o-truck')
                ->requiresConfirmation()
                ->visible(fn () =>
                    $this->record->status === Order::STATUS_CONFIRMED
                    && $this->record->fulfillment_method === 'delivery'
                )
                ->action(function () {
                    $this->record->update(['status' => Order::STATUS_ON_DELIVERY]);

                    Notification::make()->title('Order marked as On Delivery.')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            // Ready to Pickup -> Delivered (pickup completed)
            Actions\Action::make('mark_delivered_pickup')
                ->label('Mark Delivered')
                ->icon('heroicon-o-check-badge')
                ->requiresConfirmation()
                ->visible(fn () =>
                    $this->record->status === Order::STATUS_READY_TO_PICKUP
                    && $this->record->fulfillment_method === 'pickup'
                )
                ->action(function () {
                    $this->record->update(['status' => Order::STATUS_DELIVERED]);

                    Notification::make()->title('Order marked as Delivered.')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            // On Delivery -> Delivered (delivery completed)
            Actions\Action::make('mark_delivered_delivery')
                ->label('Mark Delivered')
                ->icon('heroicon-o-check-badge')
                ->requiresConfirmation()
                ->visible(fn () =>
                    $this->record->status === Order::STATUS_ON_DELIVERY
                    && $this->record->fulfillment_method === 'delivery'
                )
                ->action(function () {
                    $this->record->update(['status' => Order::STATUS_DELIVERED]);

                    Notification::make()->title('Order marked as Delivered.')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('mark_cod_paid')
                ->label('Mark Paid (COD/Pickup)')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->visible(function () {
                    if ($this->record->payment_status === 'paid') return false;

                    return $this->record->paymentIntents()
                        ->whereHas('method', fn ($q) => $q->whereIn('code', ['COD', 'PICKUP']))
                        ->exists();
                })
                ->action(function () {
                    DB::transaction(function () {
                        $order = $this->record->fresh();

                        $order->payment_status = 'paid';
                        if ($order->status === 'pending') {
                            $order->status = 'confirmed';
                        }
                        $order->save();

                        $intent = $order->paymentIntents()
                            ->whereHas('method', fn ($q) => $q->whereIn('code', ['COD', 'PICKUP']))
                            ->latest()
                            ->first();

                        if ($intent) {
                            $intent->status = 'captured';
                            $intent->save();
                        }
                    });

                    Notification::make()
                        ->title('Marked as Paid.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'payment_status']);
                }),

            Actions\Action::make('cancel_order')
                ->label('Cancel Order')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => !in_array($this->record->status, ['cancelled', 'delivered', 'refunded'], true))
                ->action(function (InventoryReservationService $inv) {
                    DB::transaction(function () use ($inv) {
                        $order = $this->record->fresh();

                        // releases only "reserved" rows, safe
                        $inv->releaseOrder($order);

                        $order->status = Order::STATUS_CANCELED;
                        $order->save();
                    });

                    Notification::make()
                        ->title('Order cancelled and reserved stock released.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('mark_refunded')
                ->label('Mark Refunded')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->payment_status === 'paid' && $this->record->status !== Order::STATUS_REFUNDED)
                ->action(function () {
                    $this->record->update([
                        'status' => Order::STATUS_REFUNDED,
                        'payment_status' => 'refunded',
                    ]);

                    Notification::make()->title('Order marked as Refunded.')->success()->send();
                    $this->refreshFormData(['status','payment_status']);
                }),


        ];
    }
}
