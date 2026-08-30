<?php

namespace App\Models;

use App\Enums\ChurchOnboardingStatus;
use App\Enums\ChurchOnboardingStep;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchOnboardingState extends Model
{
    use BelongsToChurch;

    protected $fillable = ['status', 'current_step', 'started_at', 'completed_at', 'dismissed_at', 'last_actor_user_id'];

    protected function casts(): array
    {
        return [
            'status' => ChurchOnboardingStatus::class,
            'current_step' => ChurchOnboardingStep::class,
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'dismissed_at' => 'immutable_datetime',
        ];
    }

    public function lastActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_actor_user_id');
    }
}
