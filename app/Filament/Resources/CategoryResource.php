<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationGroup = 'Catalog';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Category Details')
                ->schema([
                    
                    Forms\Components\Select::make('parent_id')
                        ->label('Parent Category')
                        ->searchable()
                        ->preload()
                        ->options(fn () =>
                            \App\Models\Category::query()
                                ->whereIn('level', [1, 2]) // only allow parent from L1/L2
                                ->orderBy('level')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($c) => [
                                    $c->id => ($c->level == 2 ? '— ' : '') . $c->name . " (L{$c->level})",
                                ])
                                ->toArray()
                        )
                        ->helperText('Leave empty for Level 1 category'),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->live(onBlur: true)
                        ->maxLength(190)
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            $set('slug', Str::slug($state));
                        }),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(190)
                        ->unique(ignoreRecord: true),

                    Forms\Components\Textarea::make('description')
                        ->rows(4)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('image_url')
                        ->label('Image URL')
                        ->maxLength(500)
                        ->placeholder('https://...'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),

                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                ])
                ->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        return str_repeat('— ', max(0, $record->level - 1)) . $state;
                    }),

                Tables\Columns\TextColumn::make('level')
                    ->label('Level')
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('level')
                    ->options([
                        1 => 'Level 1',
                        2 => 'Level 2',
                        3 => 'Level 3',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        // Calculate level based on parent
        $data['level'] = 1;

        if (!empty($data['parent_id'])) {
            $parent = Category::find($data['parent_id']);
            $data['level'] = $parent ? ($parent->level + 1) : 1;
        }

        // Enforce max 3 levels
        if (($data['level'] ?? 1) > 3) {
            Notification::make()
                ->title('Invalid Category Level')
                ->body('You can only create up to 3 category levels.')
                ->danger()
                ->send();

            abort(422, 'Max category depth is 3.');
        }

        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        // Same logic for edit/update
        $data['level'] = 1;

        if (!empty($data['parent_id'])) {
            $parent = Category::find($data['parent_id']);
            $data['level'] = $parent ? ($parent->level + 1) : 1;
        }

        if (($data['level'] ?? 1) > 3) {
            Notification::make()
                ->title('Invalid Category Level')
                ->body('You can only create up to 3 category levels.')
                ->danger()
                ->send();

            abort(422, 'Max category depth is 3.');
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
