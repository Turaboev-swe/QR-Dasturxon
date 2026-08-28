<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\TranslatableTabs;
use App\Models\Restaurant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
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
                    ->required(),
                TranslatableTabs::input('name_translations', 'Nomi'),
                TextInput::make('sort_order')
                    ->label('Tartib raqami')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Faol')
                    ->default(true)
                    ->required(),
            ]);
    }
}
