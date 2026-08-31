<?php

namespace App\Dashboard\Charts;

use App\Filament\Pages\Campaigns;
use App\Models\Campaign;
use App\Models\Church;

final readonly class CampaignActivityChartQuery
{
    public function __construct(private DailyChartBuckets $buckets) {}

    public function for(Church $church): DashboardChart
    {
        $timezone = $church->timezone ?: 'UTC';
        $timestamps = Campaign::query()->where('church_id', $church->id)
            ->where('created_at', '>=', $this->buckets->startUtc($timezone))->pluck('created_at');

        return new DashboardChart(
            'campaigns', 'Campaign activity', 'Campaigns created for coordinated Church communication.', 'Last 30 days',
            [new DashboardChartSeries('created', 'Campaigns created', $this->buckets->from($timestamps, $timezone))],
            Campaigns::getUrl(), 'Open Campaigns', 'No campaign activity yet',
            'Campaigns created in Keryon will begin building this trend.', 'Create a campaign',
        );
    }
}
