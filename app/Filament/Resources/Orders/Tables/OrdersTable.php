<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use App\Models\Restaurant;
use Filament\Actions\ViewAction;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Read-only — buyurtmalar tarixiy moliyaviy yozuv (no edit/delete actions,
 * no bulk actions). See OrderResource::canCreate()/canEdit()/canDeleteAny().
 */
class OrdersTable
{
    private const STATUS_COLORS = [
        Order::STATUS_PENDING => Color::Amber,
        Order::STATUS_CONFIRMED => Color::Blue,
        Order::STATUS_PREPARING => Color::Blue,
        Order::STATUS_READY => Color::Teal,
        Order::STATUS_SERVED => Color::Green,
        Order::STATUS_PAID => Color::Green,
        Order::STATUS_CANCELLED => Color::Red,
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('№')
                    ->sortable(),
                TextColumn::make('restaurant.name')
                    ->label('Restoran')
                    ->getStateUsing(fn (Order $record) => $record->restaurant?->translate('name_translations', 'uz')),
                TextColumn::make('table.code')
                    ->label('Stol'),
                TextColumn::make('telegramUser.first_name')
                    ->label('Mijoz'),
                TextColumn::make('status')
                    ->label('Holati')
                    ->badge()
                    ->color(fn (string $state) => self::STATUS_COLORS[$state] ?? Color::Gray),
                TextColumn::make('payment_status')
                    ->label("To'lov")
                    ->badge()
                    ->color(fn (string $state) => $state === Order::PAYMENT_STATUS_PAID ? Color::Green : Color::Gray),
                TextColumn::make('total_price')
                    ->label('Jami')
                    ->numeric()
                    ->suffix(" so'm")
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Sana')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('restaurant_id')
                    ->label('Restoran')
                    ->options(fn () => Restaurant::query()->get()->mapWithKeys(
                        fn (Restaurant $r) => [$r->id => $r->translate('name_translations', 'uz')],
                    )),
                SelectFilter::make('status')
                    ->label('Holati')
                    ->options(array_combine(Order::STATUSES, Order::STATUSES)),
                SelectFilter::make('payment_status')
                    ->label("To'lov holati")
                    ->options([
                        Order::PAYMENT_STATUS_UNPAID => "To'lanmagan",
                        Order::PAYMENT_STATUS_PAID => "To'langan",
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
