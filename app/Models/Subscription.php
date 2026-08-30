<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Subscription extends Model
{
    protected $fillable = [
        'church_id', 'billing_account_id', 'pricing_market_id', 'status', 'effective_slot', 'started_at',
        'trial_started_at', 'trial_ends_at', 'current_period_start', 'current_period_end',
        'cancel_at_period_end', 'cancelled_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'effective_slot' => 'integer',
            'started_at' => 'datetime', 'trial_started_at' => 'datetime', 'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime', 'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean', 'cancelled_at' => 'datetime', 'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $subscription) => $subscription->uuid ??= (string) Str::uuid());
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    public function pricingMarket(): BelongsTo
    {
        return $this->belongsTo(PricingMarket::class);
    }

    public function item(): HasOne
    {
        return $this->hasOne(SubscriptionItem::class);
    }
}
