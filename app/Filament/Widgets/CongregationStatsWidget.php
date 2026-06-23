<?php

namespace App\Filament\Widgets;

use App\Models\CongregationMember;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CongregationStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Members', CongregationMember::count()),
            Stat::make('Visitors', CongregationMember::where('status', 'visitor')->count()),
            Stat::make('Birthdays This Month', CongregationMember::whereNotNull('birthday')
                ->whereMonth('birthday', now()->month)
                ->count()),
            Stat::make('Inactive Members', CongregationMember::where('status', 'inactive')->count()),
        ];
    }
}
