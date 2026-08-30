<?php

namespace App\Onboarding;

use App\Commercial\Pricing\PricingService;
use App\Enums\BillingInterval;
use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\Price;
use App\Models\PricingMarket;
use DomainException;

class CurrentLaunchProductResolver
{
    public function __construct(private readonly PricingService $pricing) {}

    /** @return array{0: PlanVersion, 1: Price} */
    public function resolve(PricingMarket $market, BillingInterval $interval): array
    {
        $plan = Plan::query()->where('slug', config('commercial.current_product.plan_slug'))->where('status', PlanStatus::ACTIVE->value)->first();
        $version = $plan?->versions()->where('version_code', config('commercial.current_product.plan_version'))->first();
        if (! $version || ! $version->status->isRuntimeEligible()) {
            throw new DomainException('The configured launch PlanVersion is unavailable.');
        }
        $decision = $this->pricing->quote($version, $market, $interval);
        $price = $decision->resolved ? Price::query()->find($decision->priceId) : null;
        if (! $price) {
            throw new DomainException('The configured launch Price is unavailable.');
        }

        return [$version, $price];
    }
}
