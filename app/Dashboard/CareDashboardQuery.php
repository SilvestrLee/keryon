<?php

namespace App\Dashboard;

use App\Enums\PrayerRequestStatus;
use App\Models\PrayerRequest;

/** This query may only be resolved after CareView authorization succeeds. */
final class CareDashboardQuery
{
    /** @return array{new: int, open: int} */
    public function attention(): array
    {
        $counts = PrayerRequest::query()
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as new_count', [PrayerRequestStatus::NEW->value])
            ->selectRaw('sum(case when status != ? then 1 else 0 end) as open_count', [PrayerRequestStatus::CLOSED->value])
            ->first();

        return ['new' => (int) $counts?->new_count, 'open' => (int) $counts?->open_count];
    }
}
