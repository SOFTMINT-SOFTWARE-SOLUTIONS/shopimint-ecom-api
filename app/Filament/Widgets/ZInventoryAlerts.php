<?php

namespace App\Filament\Widgets;

use App\Models\InventoryLevel;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ZInventoryAlerts extends BaseWidget
{
    protected static ?string $heading = 'Inventory Alerts';
    protected int|string|array $columnSpan = 'full';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(
                InventoryLevel::query()
                    ->with(['inventoryItem.variant'])
                    ->where(function (Builder $q) {
                        // Low stock threshold: adjust to your preference
                        $q->whereRaw('(available - reserved) <= 3');
                    })
                    ->orderByRaw('(available - reserved) asc')
                    ->limit(30)
            )
            ->columns([
                Tables\Columns\TextColumn::make('inventoryItem.variant.sku')->label('SKU')->searchable(),
                Tables\Columns\TextColumn::make('inventoryItem.variant.title')->label('Variant')->limit(30),
                Tables\Columns\TextColumn::make('available')->sortable(),
                Tables\Columns\TextColumn::make('reserved')->sortable(),
                Tables\Columns\TextColumn::make('free_stock')
                    ->label('Free')
                    ->getStateUsing(fn ($record) => (int)$record->available - (int)$record->reserved)
                    ->badge(),
            ]);
    }
}
