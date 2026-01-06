<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Variant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Validation\Rules\Unique;
use App\Models\ProductOptionValue;

use App\Models\InventoryItem;
use App\Models\InventoryLevel;
use App\Models\Location;
use Illuminate\Support\Facades\DB;


class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variants';
    protected static ?string $icon = 'heroicon-o-squares-2x2';

    public ?int $inventoryAvailableTemp = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(190),

                    Forms\Components\TextInput::make('sku')
                        ->required()
                        ->maxLength(100)
                        ->rule(function (?Variant $record) {
                            return (new Unique('variants', 'sku'))->ignore($record?->id);
                        })
                        ->helperText('Unique SKU required.'),

                    Forms\Components\Select::make('optionValues')
                        ->label('Option Values')
                        ->multiple()
                        ->relationship('optionValues', 'value')
                        ->preload()
                        ->searchable()
                        ->helperText('Select values like Black, 256GB for this variant.')
                        ->options(function () {
                            $product = $this->getOwnerRecord();
                            if (!$product) return [];

                            return ProductOptionValue::query()
                                ->whereHas('option', fn ($q) => $q->where('product_id', $product->id))
                                ->orderBy('value')
                                ->pluck('value', 'id')
                                ->toArray();
                        }),

                    Forms\Components\TextInput::make('barcode')
                        ->maxLength(100),

                    Forms\Components\Select::make('currency')
                        ->options([
                            'LKR' => 'LKR',
                            'USD' => 'USD',
                        ])
                        ->default('LKR')
                        ->required(),

                    Forms\Components\Toggle::make('is_active')->default(true),

                    Forms\Components\Toggle::make('is_default')
                        ->label('Default variant')
                        ->helperText('Only one default per product.')
                        ->live(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Inventory (Main Shop)')
                ->schema([
                    Forms\Components\TextInput::make('inventory_available')
                        ->label('Available Stock')
                        ->numeric()
                        ->minValue(0)
                        ->default(function ($record) {
                            if (!$record) return 0;
                            return $this->getOrCreateMainLevel($record->id)->available;
                        })
                        ->helperText('Editable. This is your sellable stock.'),

                    Forms\Components\TextInput::make('inventory_reserved')
                        ->label('Reserved')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false)
                        ->default(function ($record) {
                            if (!$record) return 0;
                            return $this->getOrCreateMainLevel($record->id)->reserved;
                        })
                        ->helperText('Read-only. Reserved stock (pending checkouts).'),
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
                        ->minValue(0),

                    Forms\Components\TextInput::make('cost_price')
                        ->numeric()
                        ->prefix('Rs')
                        ->minValue(0),
                ])
                ->columns(3),

            Forms\Components\Section::make('Variant Image')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('variant_image')
                        ->collection('variant_image')
                        ->image()
                        ->imageEditor()
                        ->helperText('Single image for this variant.'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('id') // optional
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sku')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('price')->money('LKR')->sortable(),
                Tables\Columns\IconColumn::make('is_default')->boolean()->label('Default'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\TextColumn::make('main_stock')
                    ->label('Stock (Main)')
                    ->state(function ($record) {
                        $level = $this->getOrCreateMainLevel($record->id);
                        return ($level->available - $level->reserved);
                    }),
                Tables\Columns\TextColumn::make('updated_at')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        // keep inventory values aside (not part of variants table)
                        $this->inventoryAvailableTemp = (int) ($data['inventory_available'] ?? 0);
                        unset($data['inventory_available'], $data['inventory_reserved']);
                        return $data;
                    })
                    ->after(function (\App\Models\Variant $record) {
                        $level = $this->getOrCreateMainLevel($record->id);

                        DB::transaction(function () use ($level) {
                            $level->available = max(0, (int) ($this->inventoryAvailableTemp ?? 0));
                            $level->save();
                        });
                    }),
            ])
            ->actions([
                
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        $this->inventoryAvailableTemp = (int) ($data['inventory_available'] ?? 0);
                        unset($data['inventory_available'], $data['inventory_reserved']);
                        return $data;
                    })
                    ->after(function (\App\Models\Variant $record) {
                        $level = $this->getOrCreateMainLevel($record->id);

                        DB::transaction(function () use ($level) {
                            // Safety: don’t allow available < reserved
                            $newAvailable = max(0, (int) ($this->inventoryAvailableTemp ?? 0));
                            $level->available = max($newAvailable, (int)$level->reserved);
                            $level->save();
                        });
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    private function enforceSingleDefaultVariant(Variant $savedVariant): void
    {
        if (!$savedVariant->is_default) return;

        // Set all other variants of this product to not default
        $this->getOwnerRecord()
            ->variants()
            ->where('id', '!=', $savedVariant->id)
            ->update(['is_default' => 0]);
    }

    private function mainLocationId(): int
    {
        return Location::where('code', 'MAIN_SHOP')->value('id')
            ?? Location::firstOrFail()->id;
    }

    private function getOrCreateMainLevel(int $variantId): InventoryLevel
    {
        $item = InventoryItem::firstOrCreate(['variant_id' => $variantId]);

        return InventoryLevel::firstOrCreate(
            ['inventory_item_id' => $item->id, 'location_id' => $this->mainLocationId()],
            ['available' => 0, 'reserved' => 0]
        );
    }

}
