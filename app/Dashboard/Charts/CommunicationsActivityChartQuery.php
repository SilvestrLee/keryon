<?php

namespace App\Dashboard\Charts;

use App\Filament\Resources\ContentItemResource;
use App\Models\Church;
use App\Models\ContentItem;

final readonly class CommunicationsActivityChartQuery
{
    public function __construct(private DailyChartBuckets $buckets) {}

    public function for(Church $church): DashboardChart
    {
        $timezone = $church->timezone ?: 'UTC';
        $rows = ContentItem::query()
            ->where('church_id', $church->id)
            ->where(fn ($query) => $query->where('created_at', '>=', $this->buckets->startUtc($timezone))->orWhere('approved_at', '>=', $this->buckets->startUtc($timezone)))
            ->get(['created_at', 'approved_at']);

        return new DashboardChart(
            'communications', 'Communications activity', 'Content created and approved through Content Studio.', 'Last 30 days',
            [
                new DashboardChartSeries('created', 'Content created', $this->buckets->from($rows->pluck('created_at'), $timezone)),
                new DashboardChartSeries('approved', 'Content approved', $this->buckets->from($rows->pluck('approved_at'), $timezone)),
            ],
            ContentItemResource::getUrl('index'), 'Open Content Studio', 'No communications activity yet',
            'Create your first piece of content and Keryon will begin building this trend.', 'Create content',
        );
    }
}
