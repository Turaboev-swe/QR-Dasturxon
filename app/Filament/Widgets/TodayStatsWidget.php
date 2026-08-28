<?php

namespace App\Filament\Widgets;

use App\Filament\Support\BaseWindowStatsWidget;

class TodayStatsWidget extends BaseWindowStatsWidget
{
    protected static ?int $sort = 1;

    protected function getWindowDays(): int
    {
        return 1;
    }

    protected function getWindowHeading(): string
    {
        return 'Bugun';
    }
}
