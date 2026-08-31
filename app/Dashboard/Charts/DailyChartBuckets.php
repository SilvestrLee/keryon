<?php

namespace App\Dashboard\Charts;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class DailyChartBuckets
{
    public const DAYS = 30;

    /** @param iterable<DateTimeInterface|string|null> $timestamps @return list<DashboardChartPoint> */
    public function from(iterable $timestamps, string $timezone): array
    {
        $end = CarbonImmutable::now($timezone)->startOfDay();
        $start = $end->subDays(self::DAYS - 1);
        $counts = array_fill_keys(
            collect(range(0, self::DAYS - 1))->map(fn (int $offset): string => $start->addDays($offset)->toDateString())->all(),
            0,
        );

        foreach ($timestamps as $timestamp) {
            if ($timestamp === null) {
                continue;
            }

            $day = CarbonImmutable::parse($timestamp)->setTimezone($timezone)->toDateString();
            if (array_key_exists($day, $counts)) {
                $counts[$day]++;
            }
        }

        return collect($counts)->map(
            fn (int $value, string $date): DashboardChartPoint => new DashboardChartPoint(
                $date,
                CarbonImmutable::parse($date, $timezone)->format('j M'),
                $value,
            )
        )->values()->all();
    }

    public function startUtc(string $timezone): CarbonImmutable
    {
        return CarbonImmutable::now($timezone)->startOfDay()->subDays(self::DAYS - 1)->utc();
    }
}
