<?php

namespace App\Models;

use App\Enums\PrayerRequestStatus;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrayerRequest extends Model
{
    use BelongsToChurch;
    use SoftDeletes;

    protected $attributes = [
        'status' => 'new',
        'visibility' => 'private',
    ];

    protected $fillable = [
        'congregation_member_id',
        'requester_name',
        'requester_email',
        'requester_phone',
        'title',
        'request',
        'status',
        'visibility',
        'submitted_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PrayerRequestStatus::class,
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function congregationMember(): BelongsTo
    {
        return $this->belongsTo(CongregationMember::class);
    }
}
