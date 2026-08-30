<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $fillable = ['invoice_id', 'church_id', 'subscription_id', 'subscription_item_id', 'plan_version_id', 'price_id', 'pricing_market_id', 'billing_interval', 'period_start', 'period_end', 'description', 'quantity', 'currency', 'unit_amount_minor', 'subtotal_minor', 'tax_minor', 'total_minor', 'idempotency_key'];

    protected function casts(): array
    {
        return ['period_start' => 'datetime', 'period_end' => 'datetime', 'quantity' => 'integer', 'unit_amount_minor' => 'integer', 'subtotal_minor' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            if ($line->exists && $line->invoice()->where('status', '!=', 'draft')->exists()) {
                throw new DomainException('Issued Invoice lines are immutable.');
            }
        });
        static::deleting(function (self $line): void {
            if ($line->invoice()->where('status', '!=', 'draft')->exists()) {
                throw new DomainException('Issued Invoice lines cannot be deleted.');
            }
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function subscriptionItem(): BelongsTo
    {
        return $this->belongsTo(SubscriptionItem::class);
    }
}
