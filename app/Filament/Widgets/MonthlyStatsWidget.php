<?php

namespace App\Filament\Widgets;

use App\Filament\Support\BaseWindowStatsWidget;

class MonthlyStatsWidget extends BaseWindowStatsWidget
{
    protected static ?int $sort = 3;

    protected function getWindowDays(): int
    {
        return 30;
    }

    protected function getWindowHeading(): string
    {
        return 'Oxirgi 30 kun';
    }
}
