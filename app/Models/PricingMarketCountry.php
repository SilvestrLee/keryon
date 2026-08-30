<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingMarketCountry extends Model
{
    protected $fillable = ['pricing_market_id', 'country_code'];

    public function market(): BelongsTo
    {
        return $this->belongsTo(PricingMarket::class, 'pricing_market_id');
    }
}
