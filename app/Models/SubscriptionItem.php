<?php

namespace App\Models;

use App\Enums\SubscriptionItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionItem extends Model
{
    protected $fillable = ['subscription_id', 'plan_version_id', 'price_id', 'quantity', 'status', 'effective_from', 'effective_until'];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionItemStatus::class,
            'quantity' => 'integer',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
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
