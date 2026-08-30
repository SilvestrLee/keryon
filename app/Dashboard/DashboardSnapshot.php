<?php

namespace App\Dashboard;

final readonly class DashboardSnapshot
{
    /**
     * @param  list<DashboardAction>  $actions
     * @param  list<DashboardAction>  $guidance
     * @param  array<string, list<DashboardMetric>>  $summaries
     * @param  list<array{label: string, description: string, destination: string}>  $shortcuts
     * @param  array{label: string, days_remaining: int, ends_at: string}|null  $trial
     */
    public function __construct(
        public string $churchName,
        public array $actions,
        public array $guidance,
        public array $summaries,
        public array $shortcuts,
        public ?array $trial,
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
        ];
    }
}
