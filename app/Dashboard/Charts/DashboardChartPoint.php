<?php

namespace App\Dashboard\Charts;

final readonly class DashboardChartPoint
{
    public function __construct(
        public string $date,
        public string $label,
        public int $value,
    ) {}
}
