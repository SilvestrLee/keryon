<?php

namespace App\Dashboard\Charts;

use App\Filament\Pages\FaithFlow;
use App\Models\Church;
use App\Models\FaithFlowUsage;

final readonly class FaithFlowUsageChartQuery
{
    public function __construct(private DailyChartBuckets $buckets) {}

    public function for(Church $church): DashboardChart
    {
        $timezone = $church->timezone ?: 'UTC';
        $timestamps = FaithFlowUsage::query()->where('church_id', $church->id)
            ->where('created_at', '>=', $this->buckets->startUtc($timezone))->pluck('created_at');

        return new DashboardChart(
            'faithflow', 'FaithFlow usage', 'Recorded FaithFlow operations without prompts or generated content.', 'Last 30 days',
            [new DashboardChartSeries('operations', 'Operations', $this->buckets->from($timestamps, $timezone))],
            FaithFlow::getUrl(), 'Open FaithFlow', 'No FaithFlow operations yet',
            'Operations will appear here when FaithFlow processing is available and used.', 'Open FaithFlow',
        );
    }
}
