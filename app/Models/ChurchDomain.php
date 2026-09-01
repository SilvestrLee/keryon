<?php

namespace App\Models;

use App\Enums\DomainStatus;
use App\Enums\DomainTlsStatus;
use App\Enums\DomainVerificationMethod;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChurchDomain extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'normalized_hostname', 'display_hostname', 'status', 'verification_method',
        'verification_token_hash', 'ownership_verified_at', 'routing_verified_at',
        'tls_status', 'tls_ready_at', 'is_primary', 'last_checked_at',
        'consecutive_failures', 'failure_code', 'activated_at', 'disabled_at',
        'released_at', 'created_by_church_membership_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $domain): void {
            $domain->uuid ??= (string) Str::uuid();
        });

        static::deleting(fn (): never => throw new \LogicException('Church domain evidence cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'verification_method' => DomainVerificationMethod::class,
            'tls_status' => DomainTlsStatus::class,
            'is_primary' => 'boolean',
            'ownership_verified_at' => 'immutable_datetime',
            'routing_verified_at' => 'immutable_datetime',
            'tls_ready_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    public function creatorMembership(): BelongsTo
    {
        return $this->belongsTo(ChurchMembership::class, 'created_by_church_membership_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ChurchDomainEvent::class);
    }

    public function scopeEligible(Builder $query): Builder
    {
        return $query->where('status', DomainStatus::Active->value)
            ->whereNotNull('ownership_verified_at')
            ->whereNotNull('routing_verified_at')
            ->where('tls_status', DomainTlsStatus::Ready->value)
            ->whereNotNull('tls_ready_at')
            ->whereNull('disabled_at')
            ->whereNull('released_at');
    }

    public function isEligible(): bool
    {
        return $this->status === DomainStatus::Active
            && $this->ownership_verified_at !== null
            && $this->routing_verified_at !== null
            && $this->tls_status === DomainTlsStatus::Ready
            && $this->tls_ready_at !== null
            && $this->disabled_at === null
            && $this->released_at === null;
    }
}
