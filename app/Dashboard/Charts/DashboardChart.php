<?php

namespace App\Dashboard\Charts;

final readonly class DashboardChart
{
    /** @param list<DashboardChartSeries> $series */
    public function __construct(
        public string $key,
        public string $title,
        public string $description,
        public string $rangeLabel,
        public array $series,
        public string $destination,
        public string $actionLabel,
        public string $emptyTitle,
        public string $emptyDescription,
        public ?string $emptyActionLabel = null,
    ) {}

    public function hasData(): bool
    {
        return collect($this->series)->contains(fn (DashboardChartSeries $series): bool => $series->total() > 0);
    }

    public function total(): int
    {
        return array_sum(array_map(fn (DashboardChartSeries $series): int => $series->total(), $this->series));
    }

    public function maximum(): int
    {
        return max(1, ...array_map(
            fn (DashboardChartSeries $series): int => max(0, ...array_map(fn (DashboardChartPoint $point): int => $point->value, $series->points)),
            $this->series,
        ));
    }

    /** @return list<string> */
    public function axisLabels(): array
    {
        $points = $this->series[0]->points ?? [];
        if ($points === []) {
            return [];
        }

        $indexes = array_unique([0, (int) floor((count($points) - 1) / 2), count($points) - 1]);

        return array_map(fn (int $index): string => $points[$index]->label, $indexes);
    }

    /** @return list<array{x: float, y: float, point: DashboardChartPoint}> */
    public function plot(DashboardChartSeries $series): array
    {
        $last = max(1, count($series->points) - 1);
        $maximum = $this->maximum();

        return array_map(fn (DashboardChartPoint $point, int $index): array => [
            'x' => 4 + (($index / $last) * 92),
            'y' => 88 - (($point->value / $maximum) * 76),
            'point' => $point,
        ], $series->points, array_keys($series->points));
    }
}
