<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';
    protected static ?string $title = 'Options';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(50)
                ->helperText('Example: Color, Storage, Strap'),

            Forms\Components\TextInput::make('position')
                ->numeric()
                ->default(0),

            Forms\Components\Repeater::make('values')
                ->label('Option Values')
                ->relationship('values')
                ->reorderable()
                ->schema([
                    Forms\Components\TextInput::make('value')
                        ->required()
                        ->maxLength(50)
                        ->placeholder('Example: Black / 256GB'),

                    Forms\Components\TextInput::make('position')
                        ->numeric()
                        ->default(0),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->helperText('Add values for this option'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('position')->sortable(),
                Tables\Columns\TextColumn::make('values_count')
                    ->counts('values')
                    ->label('Values'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
