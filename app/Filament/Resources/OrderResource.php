<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;

use Filament\Forms\Components\Select;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Order Info')
                ->schema([
                    Forms\Components\TextInput::make('order_number')->disabled(),
                    Select::make('status')
                        ->options(Order::statusOptions())
                        ->required(),
                    Forms\Components\Select::make('payment_status')
                        ->options([
                            'unpaid' => 'Unpaid',
                            'authorized' => 'Authorized',
                            'paid' => 'Paid',
                            'failed' => 'Failed',
                            'refunded' => 'Refunded',
                        ]),
                ])
                ->columns(2),

            Forms\Components\Section::make('Guest / Customer')
                ->schema([
                    Forms\Components\TextInput::make('guest_name')->disabled(),
                    Forms\Components\TextInput::make('guest_phone')->disabled(),
                    Forms\Components\TextInput::make('guest_email')->disabled(),
                ])
                ->columns(3),

            Forms\Components\Section::make('Totals')
                ->schema([
                    Forms\Components\TextInput::make('subtotal')->prefix('Rs')->disabled(),
                    Forms\Components\TextInput::make('shipping_total')->prefix('Rs')->disabled(),
                    Forms\Components\TextInput::make('grand_total')->prefix('Rs')->disabled(),
                ])
                ->columns(3),

            Section::make('Shipping & Tracking')
                ->columns(2)
                ->schema([
                    TextInput::make('courier_company')
                        ->label('Courier Company')
                        ->placeholder('e.g. Pronto, Domex, DHL')
                        ->maxLength(100),

                    TextInput::make('tracking_number')
                        ->label('Tracking Number')
                        ->placeholder('Enter tracking number')
                        ->maxLength(100),

                    DateTimePicker::make('shipped_at')
                        ->label('Shipped At')
                        ->seconds(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('payment_status')->badge(),
                Tables\Columns\TextColumn::make('grand_total')->money('LKR'),
                Tables\Columns\TextColumn::make('fulfillment_method')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'processing' => 'Processing',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            // 'create' => Pages\CreateOrder::route('/create'),
            'edit'  => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\OrderResource\RelationManagers\ItemsRelationManager::class,
            \App\Filament\Resources\OrderResource\RelationManagers\PaymentIntentsRelationManager::class,
            \App\Filament\Resources\OrderResource\RelationManagers\InventoryReservationsRelationManager::class, // optional
        ];
    }

}
