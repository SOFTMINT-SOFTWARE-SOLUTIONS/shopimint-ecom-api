<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Filament\Resources\ProductResource\RelationManagers\VariantsRelationManager;
use App\Filament\Resources\ProductResource\RelationManagers\OptionsRelationManager;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;

use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;



class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationGroup = 'Catalog';
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Product')
                ->schema([
                    Forms\Components\Select::make('product_type')
                        ->required()
                        ->options([
                            'phone' => 'Mobile Phone',
                            'accessory' => 'Accessory',
                            'watch' => 'Smart Watch',
                        ])
                        ->live(),

                    Forms\Components\Select::make('brand_id')
                        ->label('Brand')
                        ->options(fn () => Brand::query()->where('is_active', 1)->orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get) => $get('product_type') === 'phone')
                        ->helperText('Brand is required for phones.'),

                    Forms\Components\Select::make('category_id')
                        ->label('Category')
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            $cats = \App\Models\Category::query()
                                ->where('is_active', 1)
                                ->orderBy('level')
                                ->orderBy('name')
                                ->get();

                            return $cats->mapWithKeys(function ($c) {
                                $prefix = str_repeat('— ', max(0, $c->level - 1));
                                return [$c->id => $prefix . $c->name . " (L{$c->level})"];
                            })->toArray();
                        })
                        ->required()
                        ->helperText('You can select Level 1/2/3. Prefer the most specific category available.'),


                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Set $set) {
                            $set('slug', Str::slug($state));
                        }),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'active' => 'Active',
                            'archived' => 'Archived',
                        ])
                        ->default('draft')
                        ->required(),

                    Forms\Components\Toggle::make('featured')->default(false),
                ])
                ->columns(2),
            
            Section::make('Pricing & Availability')
                ->columns(2)
                ->schema([

                    FileUpload::make('featured_image')
                        ->label('Featured Image')
                        ->disk('public')
                        ->directory('products/featured')
                        ->image()
                        ->imageEditor()
                        ->maxSize(4096)
                        ->helperText('This image will be shown as the main product image on the website.'),

                    Toggle::make('in_stock')
                        ->label('In Stock')
                        ->default(true)
                        ->inline(false),

                    TextInput::make('sell_price')
                        ->label('Sell Price')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('LKR')
                        ->required(),

                    TextInput::make('compare_price')
                        ->label('Compare Price (MRP)')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('LKR')
                        ->helperText('Optional. If set, frontend can show discount badge.'),
                ]),

            Forms\Components\Section::make('Descriptions')
                ->schema([
                    Forms\Components\Textarea::make('short_description')->rows(3),
                    //Forms\Components\RichEditor::make('description')->columnSpanFull(),
                    // instead of Forms\Components\RichEditor::make('description')
                    \Awcodes\FilamentTiptapEditor\TiptapEditor::make('description')
                        ->columnSpanFull()
                        ->profile('default') // profile includes tables
                ])
                ->columns(2),
                

            Forms\Components\Section::make('Images ')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->collection('gallery')
                        ->multiple()
                        ->reorderable()
                        ->image()
                        ->imageEditor()
                        ->maxFiles(20)
                        ->helperText('Upload product gallery images. Drag to reorder.'),
                ]),

            Forms\Components\Section::make('SEO')
                ->schema([
                    Forms\Components\TextInput::make('seo_title')->maxLength(190),
                    Forms\Components\Textarea::make('seo_description')->rows(3),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                ImageColumn::make('featured_image')
                    ->label('Image')
                    ->disk('public')
                    ->height(50)
                    ->width(50)
                    ->circular(),

                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),

                TextColumn::make('sell_price')
                    ->label('Sell')
                    ->money('LKR')
                    ->sortable(),

                TextColumn::make('compare_price')
                    ->label('Compare')
                    ->money('LKR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('in_stock')
                    ->label('In Stock')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_type')->sortable(),
                Tables\Columns\TextColumn::make('brand.name')->label('Brand')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('category.name')->label('Category')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\IconColumn::make('featured')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'active' => 'Active',
                    'archived' => 'Archived',
                ]),
                Tables\Filters\SelectFilter::make('product_type')->options([
                    'phone' => 'Mobile Phone',
                    'accessory' => 'Accessory',
                    'watch' => 'Smart Watch',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ProductResource\RelationManagers\VariantsRelationManager::class,
            OptionsRelationManager::class,
        ];
    }

}
