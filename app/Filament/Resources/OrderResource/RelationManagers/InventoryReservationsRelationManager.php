<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryReservationsRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryReservations';

    protected static ?string $title = 'Inventory Reservations';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('variant.sku')->label('SKU')->searchable(),
                Tables\Columns\TextColumn::make('quantity')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('location.name')->label('Location')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ]);
    }
}
