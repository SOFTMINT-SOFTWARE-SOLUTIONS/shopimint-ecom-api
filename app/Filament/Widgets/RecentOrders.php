<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrders extends BaseWidget
{
    protected static ?string $heading = 'Recent Orders';
    protected int|string|array $columnSpan = 'full';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(Order::query()->latest()->limit(20))
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('guest_name')
                    ->label('Customer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('fulfillment_method')
                    ->badge()
                    ->label('Fulfillment'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('grand_total')
                    ->money(fn ($record) => $record->currency ?? 'LKR')
                    ->label('Total')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Created'),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->url(fn (Order $record) => \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),
            ]);
    }
}
