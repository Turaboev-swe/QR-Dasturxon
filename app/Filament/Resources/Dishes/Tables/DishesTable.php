<?php

namespace App\Filament\Resources\Dishes\Tables;

use App\Models\Dish;
use App\Models\Restaurant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DishesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nomi')
                    ->getStateUsing(fn (Dish $record) => $record->translate('name_translations', 'uz'))
                    ->weight('bold'),
                TextColumn::make('category.name')
                    ->label('Kategoriya')
                    ->getStateUsing(fn (Dish $record) => $record->category?->translate('name_translations', 'uz')),
                TextColumn::make('restaurant.name')
                    ->label('Restoran')
                    ->getStateUsing(fn (Dish $record) => $record->restaurant?->translate('name_translations', 'uz')),
                TextColumn::make('price')
                    ->label('Narxi')
                    ->numeric()
                    ->suffix(" so'm")
                    ->sortable(),
                IconColumn::make('is_available')
                    ->label('Mavjud')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Tartib')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('restaurant_id')
                    ->label('Restoran')
                    ->options(fn () => Restaurant::query()->get()->mapWithKeys(
                        fn (Restaurant $r) => [$r->id => $r->translate('name_translations', 'uz')],
                    )),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
