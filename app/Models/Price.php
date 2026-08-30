<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\PriceBookStatus;
use App\Enums\PriceStatus;
use App\Enums\TaxBehavior;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Price extends Model
{
    protected $fillable = [
        'price_book_id', 'plan_version_id', 'billing_interval', 'currency', 'amount_minor',
        'status', 'tax_behavior', 'effective_from', 'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'billing_interval' => BillingInterval::class,
            'status' => PriceStatus::class,
            'tax_behavior' => TaxBehavior::class,
            'amount_minor' => 'integer',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $price) => $price->uuid ??= (string) Str::uuid());
        static::saving(function (self $price): void {
            if ($price->amount_minor < 0) {
                throw new DomainException('Price amount_minor cannot be negative.');
            }
            $book = $price->priceBook()->with('market')->first();
            if ($book && strtoupper($price->currency) !== $book->market->currency) {
                throw new DomainException('Price currency must match its PricingMarket currency.');
            }
            if ($price->exists && $book?->status !== PriceBookStatus::DRAFT && $price->isDirty()) {
                throw new DomainException('A Price in a published PriceBook is immutable. Create a new PriceBook instead.');
            }
        });
        static::deleting(function (self $price): void {
            if ($price->priceBook()->where('status', '!=', PriceBookStatus::DRAFT->value)->exists()) {
                throw new DomainException('A Price in a published PriceBook cannot be deleted.');
            }
        });
    }

    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(PriceBook::class);
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }
}
