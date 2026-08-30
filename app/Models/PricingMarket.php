<?php

namespace App\Models;

use App\Enums\PricingMarketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PricingMarket extends Model
{
    protected $fillable = ['code', 'name', 'currency', 'status'];

    protected function casts(): array
    {
        return ['status' => PricingMarketStatus::class];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $market) => $market->uuid ??= (string) Str::uuid());
    }

    public function countries(): HasMany
    {
        return $this->hasMany(PricingMarketCountry::class);
    }

    public function priceBooks(): HasMany
    {
        return $this->hasMany(PriceBook::class);
    }
}
