<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;

/**
 * K-CHURCHWEB-001B §23 — institutional, not Website-owned (service times
 * remain true about the church regardless of whether Website exists —
 * see §22's ownership test). Not an event/calendar system — `time` is a
 * simple display string, not a recurrence engine.
 */
class ChurchServiceTime extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'label',
        'day_of_week',
        'time',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'sort_order' => 'integer',
        ];
    }
}
