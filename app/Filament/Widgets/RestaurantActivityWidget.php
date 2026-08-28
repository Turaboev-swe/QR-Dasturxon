<?php

namespace App\Filament\Widgets;

use App\Models\DailyStat;
use App\Models\Restaurant;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

/**
 * Per-restaurant activity, sourced from `daily_stats` — the pilot's key
 * "who has stopped using it" signal (see CLAUDE.md), so the last-activity
 * column is colored (green/amber/red) to surface a gone-quiet restaurant
 * at a glance, not just as a bare date.
 */
class RestaurantActivityWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Restoranlar faolligi')
            ->query(Restaurant::query())
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Restoran')
                    ->getStateUsing(fn (Restaurant $record) => $record->translate('name_translations', 'uz'))
                    ->weight('bold'),
                TextColumn::make('today_orders')
                    ->label('Bugun')
                    ->alignCenter()
                    ->getStateUsing(fn (Restaurant $record) => $this->ordersInLastDays($record->id, 1)),
                TextColumn::make('week_orders')
                    ->label('7 kun')
                    ->alignCenter()
                    ->getStateUsing(fn (Restaurant $record) => $this->ordersInLastDays($record->id, 7)),
                TextColumn::make('month_orders')
                    ->label('30 kun')
                    ->alignCenter()
                    ->getStateUsing(fn (Restaurant $record) => $this->ordersInLastDays($record->id, 30)),
                TextColumn::make('last_activity')
                    ->label('Oxirgi faollik')
                    ->badge()
                    ->getStateUsing(fn (Restaurant $record) => optional($this->lastActivityDate($record->id))->format('d.m.Y') ?? 'Hech qachon')
                    ->color(fn (Restaurant $record) => $this->lastActivityColor($record->id)),
            ]);
    }

    private function ordersInLastDays(int $restaurantId, int $days): int
    {
        $from = Carbon::today()->subDays($days - 1)->toDateString();
        $to = Carbon::today()->toDateString();

        return (int) DailyStat::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('date', [$from, $to])
            ->sum('orders_count');
    }

    private function lastActivityDate(int $restaurantId): ?Carbon
    {
        $date = DailyStat::query()->where('restaurant_id', $restaurantId)->max('date');

        return $date ? Carbon::parse($date) : null;
    }

    private function lastActivityColor(int $restaurantId): string|array
    {
        $date = $this->lastActivityDate($restaurantId);

        if (! $date) {
            return Color::Red;
        }

        return match (true) {
            $date->diffInDays(Carbon::today()) <= 1 => Color::Green,
            $date->diffInDays(Carbon::today()) <= 7 => Color::Amber,
            default => Color::Red,
        };
    }
}
