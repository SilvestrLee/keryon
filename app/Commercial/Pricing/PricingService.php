<?php

namespace App\Commercial\Pricing;

use App\Enums\BillingInterval;
use App\Enums\PlanVersionStatus;
use App\Enums\PriceBookStatus;
use App\Enums\PriceStatus;
use App\Enums\PricingDecisionReason;
use App\Enums\PricingMarketStatus;
use App\Models\PlanVersion;
use App\Models\Price;
use App\Models\PriceBook;
use App\Models\PricingMarket;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class PricingService
{
    public function quote(
        PlanVersion $planVersion,
        PricingMarket $market,
        BillingInterval $interval,
        ?DateTimeInterface $at = null,
    ): PricingDecision {
        $effectiveAt = CarbonImmutable::instance($at ?? now());

        if (! in_array($planVersion->status, [PlanVersionStatus::ACTIVE, PlanVersionStatus::RETIRED], true)) {
            return $this->denied($planVersion, $market, $interval, $effectiveAt, PricingDecisionReason::PLAN_VERSION_INELIGIBLE);
        }
        if ($market->status !== PricingMarketStatus::ACTIVE) {
            return $this->denied($planVersion, $market, $interval, $effectiveAt, PricingDecisionReason::MARKET_INACTIVE);
        }

        $books = PriceBook::query()
            ->where('pricing_market_id', $market->id)
            ->whereIn('status', [PriceBookStatus::ACTIVE->value, PriceBookStatus::RETIRED->value])
            ->where('effective_from', '<=', $effectiveAt)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $effectiveAt))
            ->get();
        if ($books->count() !== 1) {
            return $this->denied($planVersion, $market, $interval, $effectiveAt, PricingDecisionReason::PRICE_BOOK_UNAVAILABLE);
        }

        $book = $books->first();
        $prices = Price::query()
            ->where('price_book_id', $book->id)
            ->where('plan_version_id', $planVersion->id)
            ->where('billing_interval', $interval->value)
            ->where('status', PriceStatus::ACTIVE->value)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $effectiveAt))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $effectiveAt))
            ->get();
        if ($prices->count() !== 1) {
            return $this->denied($planVersion, $market, $interval, $effectiveAt, PricingDecisionReason::PRICE_UNAVAILABLE, $book->id);
        }

        $price = $prices->first();
        if ($price->currency !== $market->currency) {
            return $this->denied($planVersion, $market, $interval, $effectiveAt, PricingDecisionReason::CURRENCY_MISMATCH, $book->id);
        }

        return new PricingDecision(
            true, $market->code, $book->id, $price->id, $planVersion->id, $interval,
            $price->currency, $price->amount_minor, 'governed_price_book', PricingDecisionReason::RESOLVED, $effectiveAt,
        );
    }

    private function denied(
        PlanVersion $version,
        PricingMarket $market,
        BillingInterval $interval,
        CarbonImmutable $at,
        PricingDecisionReason $reason,
        ?int $bookId = null,
    ): PricingDecision {
        return new PricingDecision(false, $market->code, $bookId, null, $version->id, $interval, null, null, 'governed_price_book', $reason, $at);
    }
}
