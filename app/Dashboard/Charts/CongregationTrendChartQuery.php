<?php

namespace App\Dashboard\Charts;

use App\Filament\Resources\CongregationResource;
use App\Models\Church;
use App\Models\CongregationMember;

final readonly class CongregationTrendChartQuery
{
    public function __construct(private DailyChartBuckets $buckets) {}

    public function for(Church $church): DashboardChart
    {
        $timezone = $church->timezone ?: 'UTC';
        $timestamps = CongregationMember::query()->where('church_id', $church->id)
            ->where('created_at', '>=', $this->buckets->startUtc($timezone))->pluck('created_at');

        return new DashboardChart(
            'congregation', 'Congregation directory activity', 'New records added to the Church directory.', 'Last 30 days',
            [new DashboardChartSeries('records', 'Records added', $this->buckets->from($timestamps, $timezone))],
            CongregationResource::getUrl('index'), 'View congregation', 'No new congregation records yet',
            'New directory records will appear here as they are added.', 'Add a person',
        );
    }
}
