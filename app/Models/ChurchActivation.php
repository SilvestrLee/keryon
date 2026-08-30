<?php

namespace App\Models;

use App\Enums\BillingAccountOwnerType;
use App\Enums\BillingInterval;
use App\Enums\ChurchActivationStatus;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChurchActivation extends Model
{
    protected $fillable = [
        'church_id', 'prospective_primary_email', 'prospective_user_id', 'status', 'token_hash',
        'token_expires_at', 'invitation_sent_at', 'accepted_at', 'revoked_at',
        'provisioned_by_reference', 'provisioning_origin', 'pricing_market_id', 'plan_version_id',
        'price_id', 'billing_interval', 'payer_type', 'payer_church_id', 'payer_organization_id',
        'payer_organization_unit_id', 'idempotency_key', 'acceptance_idempotency_key',
        'terms_version', 'privacy_version', 'legal_accepted_at', 'legal_accepted_by_user_id',
        'organization_id', 'organization_unit_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChurchActivationStatus::class,
            'billing_interval' => BillingInterval::class,
            'payer_type' => BillingAccountOwnerType::class,
            'token_expires_at' => 'immutable_datetime', 'invitation_sent_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime',
            'legal_accepted_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $activation) => $activation->uuid ??= (string) Str::uuid());
        static::saving(function (self $activation): void {
            $expected = match ($activation->payer_type instanceof BillingAccountOwnerType ? $activation->payer_type : BillingAccountOwnerType::tryFrom((string) $activation->payer_type)) {
                BillingAccountOwnerType::CHURCH => 'payer_church_id',
                BillingAccountOwnerType::ORGANIZATION => 'payer_organization_id',
                BillingAccountOwnerType::ORGANIZATION_UNIT => 'payer_organization_unit_id',
                default => null,
            };
            $populated = collect(['payer_church_id', 'payer_organization_id', 'payer_organization_unit_id'])->filter(fn ($field) => $activation->{$field} !== null)->values()->all();
            if ($expected === null || $populated !== [$expected]) {
                throw new DomainException('ChurchActivation requires exactly one payer intent matching its bounded payer type.');
            }
        });
        static::updating(function (self $activation): void {
            $originalStatus = ChurchActivationStatus::tryFrom((string) $activation->getRawOriginal('status'));
            if (in_array($originalStatus, [ChurchActivationStatus::ACCEPTED, ChurchActivationStatus::REVOKED], true) && $activation->isDirty()) {
                throw new DomainException('A terminal ChurchActivation cannot be reopened or casually mutated.');
            }
            if ($activation->getRawOriginal('invitation_sent_at') !== null && $activation->isDirty([
                'church_id', 'prospective_primary_email', 'pricing_market_id', 'plan_version_id', 'price_id',
                'billing_interval', 'payer_type', 'payer_church_id', 'payer_organization_id',
                'payer_organization_unit_id', 'organization_id', 'organization_unit_id',
            ])) {
                throw new DomainException('An issued Church activation offer is immutable; revoke and reprovision it.');
            }
        });
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function prospectiveUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prospective_user_id');
    }

    public function pricingMarket(): BelongsTo
    {
        return $this->belongsTo(PricingMarket::class);
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class);
    }
}
