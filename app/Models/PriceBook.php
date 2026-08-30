<?php

namespace App\Models;

use App\Enums\PriceBookStatus;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PriceBook extends Model
{
    protected $fillable = [
        'pricing_market_id', 'code', 'name', 'status', 'effective_from', 'effective_until', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PriceBookStatus::class,
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $book) => $book->uuid ??= (string) Str::uuid());
        static::updating(function (self $book): void {
            if ($book->getOriginal('published_at') !== null
                && $book->isDirty(['pricing_market_id', 'code', 'effective_from'])) {
                throw new DomainException('A published PriceBook identity is immutable. Create a new PriceBook instead.');
            }
        });
        static::deleting(function (self $book): void {
            if ($book->published_at !== null) {
                throw new DomainException('A published PriceBook cannot be deleted. Retire it instead.');
            }
        });
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(PricingMarket::class, 'pricing_market_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }
}
