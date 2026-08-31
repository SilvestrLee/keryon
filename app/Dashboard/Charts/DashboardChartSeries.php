<?php

namespace App\Dashboard\Charts;

final readonly class DashboardChartSeries
{
    /** @param list<DashboardChartPoint> $points */
    public function __construct(
        public string $key,
        public string $label,
        public array $points,
    ) {}

    public function total(): int
    {
        return array_sum(array_map(fn (DashboardChartPoint $point): int => $point->value, $this->points));
    }
}
