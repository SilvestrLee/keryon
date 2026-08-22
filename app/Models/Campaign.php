<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use LogicException;

class Campaign extends Model
{
    use BelongsToChurch;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'purpose',
        'status',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $campaign): void {
            if ($campaign->starts_on !== null && $campaign->ends_on !== null && $campaign->ends_on->lt($campaign->starts_on)) {
                throw ValidationException::withMessages([
                    'ends_on' => 'The campaign end date must be on or after its start date.',
                ]);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(CampaignCommunication::class)->orderBy('sort_order');
    }

    public function mediaAssociations(): HasMany
    {
        return $this->hasMany(CampaignMedia::class)->orderBy('sort_order');
    }

    public function transitionTo(CampaignStatus $status): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw new LogicException("Campaign cannot transition from [{$this->status->value}] to [{$status->value}].");
        }

        $this->status = $status;
    }
}
