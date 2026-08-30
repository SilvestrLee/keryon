<?php

namespace App\Commercial\Pricing;

use App\Enums\BillingInterval;
use App\Enums\PricingDecisionReason;
use Carbon\CarbonImmutable;

final readonly class PricingDecision
{
    public function __construct(
        public bool $resolved,
        public ?string $marketCode,
        public ?int $priceBookId,
        public ?int $priceId,
        public int $planVersionId,
        public BillingInterval $billingInterval,
        public ?string $currency,
        public ?int $amountMinor,
        public string $source,
        public PricingDecisionReason $reason,
        public CarbonImmutable $effectiveAt,
    ) {}
}
