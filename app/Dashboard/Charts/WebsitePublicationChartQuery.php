<?php

namespace App\Dashboard\Charts;

use App\Filament\Clusters\Website\Pages\WebsiteOverview;
use App\Models\Church;
use App\Models\WebsitePublication;

final readonly class WebsitePublicationChartQuery
{
    public function __construct(private DailyChartBuckets $buckets) {}

    public function for(Church $church): DashboardChart
    {
        $timezone = $church->timezone ?: 'UTC';
        $timestamps = WebsitePublication::query()->where('church_id', $church->id)
            ->where('published_at', '>=', $this->buckets->startUtc($timezone))->pluck('published_at');

        return new DashboardChart(
            'website', 'Website publication activity', 'Recorded Website publications, not traffic or visitor analytics.', 'Last 30 days',
            [new DashboardChartSeries('published', 'Publications', $this->buckets->from($timestamps, $timezone))],
            WebsiteOverview::getUrl(), 'Open Website', 'No Website publications yet',
            'Publication activity will appear here after your Church Website is published.', 'Open Website',
        );
    }
}
