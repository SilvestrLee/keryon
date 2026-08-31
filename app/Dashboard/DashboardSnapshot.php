<?php

namespace App\Dashboard;

use App\Dashboard\Charts\DashboardChart;

final readonly class DashboardSnapshot
{
    /**
     * @param  list<DashboardAction>  $actions
     * @param  list<DashboardAction>  $guidance
     * @param  array<string, list<DashboardMetric>>  $summaries
     * @param  list<array{label: string, description: string, destination: string}>  $shortcuts
     * @param  list<DashboardChart>  $charts
     * @param  array{label: string, days_remaining: int, ends_at: string}|null  $trial
     */
    public function __construct(
        public string $churchName,
        public array $actions,
        public array $guidance,
        public array $summaries,
        public array $shortcuts,
        public ?array $trial,
        public array $charts = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'church_name' => $this->churchName,
            'actions' => array_map(fn (DashboardAction $action): array => $action->toArray(), $this->actions),
            'guidance' => array_map(fn (DashboardAction $action): array => $action->toArray(), $this->guidance),
            'summaries' => collect($this->summaries)->map(
                fn (array $metrics): array => array_map(fn (DashboardMetric $metric): array => $metric->toArray(), $metrics)
            )->all(),
            'shortcuts' => $this->shortcuts,
            'trial' => $this->trial,
            'charts' => array_map(fn (DashboardChart $chart): array => [
                'key' => $chart->key,
                'title' => $chart->title,
                'has_data' => $chart->hasData(),
                'series' => array_map(fn (Charts\DashboardChartSeries $series): array => [
                    'key' => $series->key,
                    'label' => $series->label,
                    'points' => array_map(fn (Charts\DashboardChartPoint $point): array => ['date' => $point->date, 'value' => $point->value], $series->points),
                ], $chart->series),
            ], $this->charts),
        ];
    }
}
