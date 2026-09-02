<?php

namespace App\Models;

use App\Enums\PlatformCapability;
use App\Enums\PlatformMembershipStatus;
use App\Enums\PlatformRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlatformMembership extends Model
{
    protected $fillable = [
        'user_id', 'role', 'status', 'provisioned_by_user_id',
        'activated_at', 'suspended_at', 'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => PlatformRole::class,
            'status' => PlatformMembershipStatus::class,
            'activated_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provisionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provisioned_by_user_id');
    }

    public function mfaCredential(): HasOne
    {
        return $this->hasOne(PlatformMfaCredential::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PlatformMembershipStatus::ACTIVE->value);
    }

    /** @return list<PlatformCapability> */
    public function capabilities(): array
    {
        return $this->status === PlatformMembershipStatus::ACTIVE
            ? $this->role->capabilities()
            : [];
    }

    public function hasCapability(PlatformCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }
}
