<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Models\OrderItem;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;

class OrderInfolist
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

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('id')
                            ->label('Buyurtma №'),
                        TextEntry::make('restaurant.name')
                            ->label('Restoran')
                            ->getStateUsing(fn (Order $record) => $record->restaurant?->translate('name_translations', 'uz')),
                        TextEntry::make('table.name')
                            ->label('Stol')
                            ->getStateUsing(fn (Order $record) => $record->table?->name ?: 'Stol '.$record->table?->code),
                        TextEntry::make('telegramUser.first_name')
                            ->label('Mijoz'),
                        TextEntry::make('status')
                            ->label('Holati')
                            ->badge()
                            ->color(fn (string $state) => self::STATUS_COLORS[$state] ?? Color::Gray),
                        TextEntry::make('payment_status')
                            ->label("To'lov holati")
                            ->badge()
                            ->color(fn (string $state) => $state === Order::PAYMENT_STATUS_PAID ? Color::Green : Color::Gray),
                        TextEntry::make('total_price')
                            ->label('Jami')
                            ->numeric()
                            ->suffix(" so'm"),
                        TextEntry::make('created_at')
                            ->label('Yaratilgan')
                            ->dateTime(),
                    ]),

                TextEntry::make('comment')
                    ->label('Izoh')
                    ->placeholder('—')
                    ->columnSpanFull(),

                RepeatableEntry::make('items')
                    ->label('Taomlar')
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('dish.name')
                            ->label('Taom')
                            ->getStateUsing(fn (OrderItem $record) => $record->dish?->translate('name_translations', 'uz')),
                        TextEntry::make('quantity')
                            ->label('Soni'),
                        TextEntry::make('unit_price')
                            ->label('Narxi')
                            ->numeric()
                            ->suffix(" so'm"),
                        TextEntry::make('total_price')
                            ->label('Summa')
                            ->numeric()
                            ->suffix(" so'm"),
                    ]),
            ]);
    }
}
