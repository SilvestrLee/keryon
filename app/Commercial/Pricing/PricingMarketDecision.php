<?php

namespace App\Commercial\Pricing;

use App\Models\PricingMarket;
use Carbon\CarbonImmutable;

final readonly class PricingMarketDecision
{
    public function __construct(
        public ?PricingMarket $market,
        public string $source,
        public string $reason,
        public ?string $country,
        public CarbonImmutable $resolvedAt,
    ) {}

    public function resolved(): bool
    {
        return $this->market !== null;
    }
}
