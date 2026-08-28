<?php

namespace App\Filament\Support;

use App\Models\DailyStat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Shared aggregation for the three "today / last 7 days / last 30 days"
 * dashboard stat cards — every number here is a SUM over `daily_stats`
 * rows, never a live recomputation from `orders`/`waiter_calls`/etc.
 * (that's the whole point of daily_stats — see CLAUDE.md).
 *
 * Note on "noyob foydalanuvchilar" over multi-day windows: this sums each
 * day's already-deduplicated `unique_users_count`, so a customer who
 * visits on two different days within the window is counted twice — it
 * is NOT a true period-wide distinct count (which would need querying
 * daily_restaurant_visits directly). Deliberate: it keeps every stat in
 * this widget an identical, simple SUM(daily_stats.column) matching the
 * others, at the cost of slightly over-counting repeat visitors across
 * days — negligible for a same-day view, worth knowing for 7/30-day ones.
 */
abstract class BaseWindowStatsWidget extends BaseWidget
{
    abstract protected function getWindowDays(): int;

    abstract protected function getWindowHeading(): string;

    protected function getHeading(): ?string
    {
        return $this->getWindowHeading();
    }

    protected function getStats(): array
    {
        $to = Carbon::today();
        $from = $to->copy()->subDays($this->getWindowDays() - 1);

        $totals = DailyStat::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(orders_count), 0) as orders_count')
            ->selectRaw('COALESCE(SUM(orders_total_amount), 0) as orders_total_amount')
            ->selectRaw('COALESCE(SUM(unique_users_count), 0) as unique_users_count')
            ->selectRaw('COALESCE(SUM(waiter_calls_count), 0) as waiter_calls_count')
            ->selectRaw('COALESCE(SUM(bill_requests_count), 0) as bill_requests_count')
            ->first();

        return [
            Stat::make('Buyurtmalar', (int) $totals->orders_count),
            Stat::make('Jami summa', number_format((float) $totals->orders_total_amount, 0, '.', ' ')." so'm"),
            Stat::make('Noyob foydalanuvchilar', (int) $totals->unique_users_count),
            Stat::make('Ofitsiant chaqiruvlari', (int) $totals->waiter_calls_count),
            Stat::make("Hisob so'rovlari", (int) $totals->bill_requests_count),
        ];
    }
}
