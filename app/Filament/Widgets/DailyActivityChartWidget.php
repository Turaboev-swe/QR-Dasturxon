<?php

namespace App\Filament\Widgets;

use App\Models\DailyStat;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Platform-wide (all restaurants summed together) daily orders + unique
 * users over the last 30 days — sourced entirely from `daily_stats`.
 * Days with zero activity for every restaurant simply have no row at
 * all, so the 30-day range is walked explicitly and missing days are
 * filled with 0 rather than skipped, keeping the x-axis continuous.
 */
class DailyActivityChartWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        return 'Kunlik faollik (oxirgi 30 kun)';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $to = Carbon::today();
        $from = $to->copy()->subDays(29);

        $rowsByDate = DailyStat::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('date, SUM(orders_count) as orders_count, SUM(unique_users_count) as unique_users_count')
            ->groupBy('date')
            ->get()
            ->keyBy(fn (DailyStat $row) => $row->date->toDateString());

        $labels = [];
        $orders = [];
        $uniqueUsers = [];

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $row = $rowsByDate->get($date->toDateString());

            $labels[] = $date->format('d.m');
            $orders[] = (int) ($row->orders_count ?? 0);
            $uniqueUsers[] = (int) ($row->unique_users_count ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Buyurtmalar',
                    'data' => $orders,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                ],
                [
                    'label' => 'Noyob foydalanuvchilar',
                    'data' => $uniqueUsers,
                    'borderColor' => '#14b8a6',
                    'backgroundColor' => 'rgba(20, 184, 166, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
