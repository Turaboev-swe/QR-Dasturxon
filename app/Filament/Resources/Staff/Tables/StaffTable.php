<?php

namespace App\Filament\Resources\Staff\Tables;

use App\Models\Restaurant;
use App\Models\Staff;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StaffTable
{
    private const ROLE_LABELS = [
        Staff::ROLE_WAITER => 'Ofitsiant',
        Staff::ROLE_CASHIER => 'Kassir',
        Staff::ROLE_ADMIN => 'Egasi (admin)',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Ismi')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('restaurant.name')
                    ->label('Restoran')
                    ->getStateUsing(fn (Staff $record) => $record->restaurant?->translate('name_translations', 'uz')),
                TextColumn::make('role')
                    ->label('Roli')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::ROLE_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => $state === Staff::ROLE_ADMIN ? Color::Amber : Color::Gray),
                TextColumn::make('telegram_id')
                    ->label('Telegram ID')
                    ->placeholder('Hali biriktirilmagan')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('restaurant_id')
                    ->label('Restoran')
                    ->options(fn () => Restaurant::query()->get()->mapWithKeys(
                        fn (Restaurant $r) => [$r->id => $r->translate('name_translations', 'uz')],
                    )),
                SelectFilter::make('role')
                    ->label('Roli')
                    ->options(self::ROLE_LABELS),
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
