<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use App\Models\Restaurant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nomi')
                    ->getStateUsing(fn (Category $record) => $record->translate('name_translations', 'uz'))
                    ->weight('bold'),
                TextColumn::make('restaurant.name')
                    ->label('Restoran')
                    ->getStateUsing(fn (Category $record) => $record->restaurant?->translate('name_translations', 'uz'))
                    ->searchable(false),
                TextColumn::make('dishes_count')
                    ->label('Taomlar')
                    ->counts('dishes')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Tartib')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
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
