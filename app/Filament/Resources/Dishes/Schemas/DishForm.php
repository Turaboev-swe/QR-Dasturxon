<?php

namespace App\Filament\Resources\Dishes\Schemas;

use App\Filament\Support\TranslatableTabs;
use App\Models\Category;
use App\Models\Restaurant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class DishForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('restaurant_id')
                    ->label('Restoran')
                    ->relationship('restaurant', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Restaurant $record) => $record->translate('name_translations', 'uz'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->afterStateUpdated(fn (Set $set) => $set('category_id', null)),
                Select::make('category_id')
                    ->label('Kategoriya')
                    ->relationship(
                        'category',
                        'id',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query->when(
                            $get('restaurant_id'),
                            fn (Builder $q, $restaurantId) => $q->where('restaurant_id', $restaurantId),
                        ),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Category $record) => $record->translate('name_translations', 'uz'))
                    ->searchable()
                    ->preload()
                    ->required(),

                TranslatableTabs::input('name_translations', 'Nomi'),
                TranslatableTabs::textarea('description_translations', 'Tavsif'),
                TranslatableTabs::textarea('ingredients_translations', 'Tarkib'),

                TextInput::make('price')
                    ->label('Narxi')
                    ->numeric()
                    ->required()
                    ->suffix("so'm"),
                TagsInput::make('allergens')
                    ->label('Allergenlar')
                    ->helperText('Masalan: gluten, yong\'oq, sut'),
                TextInput::make('image_path')
                    ->label('Rasm yo\'li')
                    ->helperText('Hozircha faqat matn maydoni — yuklash UI keyingi bosqichda (CLAUDE.md).'),
                TextInput::make('sort_order')
                    ->label('Tartib raqami')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_available')
                    ->label('Mavjud')
                    ->default(true)
                    ->required(),
            ]);
    }
}
