<?php

namespace App\Models;

use App\Enums\PlanVersionStatus;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PlanVersion extends Model
{
    protected $fillable = [
        'plan_id', 'version_code', 'status', 'effective_from',
        'effective_until', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PlanVersionStatus::class,
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $version) => $version->uuid ??= (string) Str::uuid());
        static::updating(function (self $version): void {
            if ($version->getOriginal('published_at') !== null
                && $version->isDirty(['plan_id', 'version_code', 'effective_from'])) {
                throw new DomainException('A published PlanVersion identity is immutable. Create a new version instead.');
            }
        });
        static::deleting(function (self $version): void {
            if ($version->published_at !== null) {
                throw new DomainException('A published PlanVersion cannot be deleted. Retire it instead.');
            }
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(PlanVersionEntitlement::class);
    }
}
