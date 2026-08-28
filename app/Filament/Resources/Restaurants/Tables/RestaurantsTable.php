<?php

namespace App\Filament\Resources\Restaurants\Tables;

use App\Models\Restaurant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RestaurantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nomi')
                    ->getStateUsing(fn (Restaurant $record) => $record->translate('name_translations', 'uz'))
                    ->searchable(query: fn ($query, string $search) => $query->where('name_translations->uz', 'like', "%{$search}%"))
                    ->weight('bold'),
                IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
                IconColumn::make('is_verified')
                    ->label('Tasdiqlangan')
                    ->boolean(),
                TextColumn::make('tables_count')
                    ->label('Stollar')
                    ->counts('tables')
                    ->sortable(),
                TextColumn::make('kitchen_chat_id')
                    ->label('Oshxona chat')
                    ->placeholder('—'),
                TextColumn::make('waiter_chat_id')
                    ->label('Ofitsiant chat')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Yaratilgan')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Faol'),
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
