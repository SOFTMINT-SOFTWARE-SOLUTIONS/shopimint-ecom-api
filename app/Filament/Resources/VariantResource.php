<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VariantResource\Pages;
use App\Models\Variant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Database\Eloquent\Builder;

class VariantResource extends Resource
{
    protected static ?string $model = Variant::class;

    protected static ?string $navigationGroup = 'Catalog';
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    // ✅ Shopify-style: manage variants under product, so hide from menu
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Variant')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(190)
                        ->helperText('Example: Black / 256GB'),

                    Forms\Components\TextInput::make('sku')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->helperText('Must be unique across all variants.'),

                    Forms\Components\TextInput::make('barcode')
                        ->maxLength(100)
                        ->helperText('Optional'),

                    Forms\Components\Select::make('currency')
                        ->options([
                            'LKR' => 'LKR',
                            'USD' => 'USD',
                        ])
                        ->default('LKR')
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),

                    Forms\Components\Toggle::make('is_default')
                        ->label('Default variant')
                        ->helperText('Only one default variant is allowed per product.'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Pricing')
                ->schema([
                    Forms\Components\TextInput::make('price')
                        ->numeric()
                        ->required()
                        ->prefix('Rs')
                        ->minValue(0),

                    Forms\Components\TextInput::make('compare_at_price')
                        ->numeric()
                        ->prefix('Rs')
                        ->minValue(0)
                        ->helperText('Original price (optional)'),

                    Forms\Components\TextInput::make('cost_price')
                        ->numeric()
                        ->prefix('Rs')
                        ->minValue(0)
                        ->helperText('Your cost (optional)'),
                ])
                ->columns(3),

            Forms\Components\Section::make('Variant Image')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('variant_image')
                        ->collection('variant_image')
                        ->image()
                        ->imageEditor()
                        ->helperText('Upload 1 image for this variant'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('product.title')->label('Product')->searchable()->toggleable(),
            Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('sku')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('price')->money('LKR')->sortable(),
            Tables\Columns\IconColumn::make('is_default')->boolean()->label('Default'),
            Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        // keep default query
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVariants::route('/'),
            'create' => Pages\CreateVariant::route('/create'),
            'edit' => Pages\EditVariant::route('/{record}/edit'),
        ];
    }
}
