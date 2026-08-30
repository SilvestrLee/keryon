<?php

namespace App\Commercial\Pricing;

use App\Enums\PricingMarketStatus;
use App\Models\PricingMarketCountry;
use Carbon\CarbonImmutable;

class PricingMarketResolver
{
    public function forCountry(?string $countryCode): PricingMarketDecision
    {
        $country = $countryCode ? strtoupper(trim($countryCode)) : null;
        if ($country === null || ! preg_match('/^[A-Z]{2}$/', $country)) {
            return new PricingMarketDecision(null, 'unresolved', 'country_unresolved', $country, CarbonImmutable::now());
        }

        $mapping = PricingMarketCountry::query()
            ->with('market')
            ->where('country_code', $country)
            ->whereHas('market', fn ($query) => $query->where('status', PricingMarketStatus::ACTIVE->value))
            ->first();

        return $mapping
            ? new PricingMarketDecision($mapping->market, 'country_mapping', 'resolved', $country, CarbonImmutable::now())
            : new PricingMarketDecision(null, 'unresolved', 'country_unmapped', $country, CarbonImmutable::now());
    }
}
