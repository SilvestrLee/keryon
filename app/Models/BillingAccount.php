<?php

namespace App\Models;

use App\Enums\BillingAccountOwnerType;
use App\Enums\BillingAccountStatus;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class BillingAccount extends Model
{
    protected $fillable = ['name', 'owner_type', 'church_id', 'organization_id', 'organization_unit_id', 'status'];

    protected function casts(): array
    {
        return ['owner_type' => BillingAccountOwnerType::class, 'status' => BillingAccountStatus::class];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $account) => $account->uuid ??= (string) Str::uuid());
        static::saving(fn (self $account) => $account->assertValidOwner());
    }

    public function assertValidOwner(): void
    {
        $expected = match ($this->owner_type instanceof BillingAccountOwnerType ? $this->owner_type : BillingAccountOwnerType::tryFrom((string) $this->owner_type)) {
            BillingAccountOwnerType::CHURCH => 'church_id',
            BillingAccountOwnerType::ORGANIZATION => 'organization_id',
            BillingAccountOwnerType::ORGANIZATION_UNIT => 'organization_unit_id',
            default => null,
        };
        $populated = collect(['church_id', 'organization_id', 'organization_unit_id'])->filter(fn ($field) => $this->{$field} !== null)->values();
        if ($expected === null || $populated->all() !== [$expected]) {
            throw new DomainException('A BillingAccount requires exactly one owner matching its bounded owner type.');
        }
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function billingProfile(): HasOne
    {
        return $this->hasOne(BillingProfile::class)->whereNull('effective_until')->latestOfMany('effective_from');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
