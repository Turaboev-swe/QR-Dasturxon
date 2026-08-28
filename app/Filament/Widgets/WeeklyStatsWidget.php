<?php

namespace App\Filament\Widgets;

use App\Filament\Support\BaseWindowStatsWidget;

class WeeklyStatsWidget extends BaseWindowStatsWidget
{
    protected static ?int $sort = 2;

    protected function getWindowDays(): int
    {
        return 7;
    }

    protected function getWindowHeading(): string
    {
        return 'Oxirgi 7 kun';
    }
}
