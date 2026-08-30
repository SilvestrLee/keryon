<?php

namespace App\Commercial\Pricing;

use App\Enums\BillingInterval;
use App\Enums\PriceBookStatus;
use App\Enums\PriceStatus;
use App\Enums\PricingMarketStatus;
use App\Enums\TaxBehavior;
use App\Models\PlanVersion;
use App\Models\Price;
use App\Models\PriceBook;
use App\Models\PricingMarket;
use App\Models\PricingMarketCountry;
use DomainException;
use Illuminate\Support\Facades\DB;

class PricingCatalogService
{
    public function createMarket(string $code, string $name, string $currency): PricingMarket
    {
        $code = strtoupper($code);
        $currency = strtoupper($currency);
        if (! preg_match('/^[A-Z]{2}$/', $code) || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainException('Market and currency codes must use ISO alpha codes.');
        }

        return PricingMarket::query()->create([
            'code' => $code, 'name' => $name, 'currency' => $currency,
            'status' => PricingMarketStatus::ACTIVE,
        ]);
    }

    public function mapCountry(PricingMarket $market, string $countryCode): PricingMarketCountry
    {
        $countryCode = strtoupper($countryCode);
        if (! preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new DomainException('Country code must use ISO 3166-1 alpha-2 form.');
        }

        return $market->countries()->create(['country_code' => $countryCode]);
    }

    public function createPriceBook(
        PricingMarket $market,
        string $code,
        string $name,
        \DateTimeInterface $effectiveFrom,
        ?\DateTimeInterface $effectiveUntil = null,
    ): PriceBook {
        if ($effectiveUntil !== null && $effectiveUntil <= $effectiveFrom) {
            throw new DomainException('PriceBook effective_until must follow effective_from.');
        }

        return $market->priceBooks()->create([
            'code' => $code, 'name' => $name, 'status' => PriceBookStatus::DRAFT,
            'effective_from' => $effectiveFrom, 'effective_until' => $effectiveUntil,
        ]);
    }

    public function definePrice(
        PriceBook $book,
        PlanVersion $version,
        BillingInterval $interval,
        int $amountMinor,
        ?\DateTimeInterface $effectiveFrom = null,
        ?\DateTimeInterface $effectiveUntil = null,
        TaxBehavior $taxBehavior = TaxBehavior::UNSPECIFIED,
    ): Price {
        if ($book->status !== PriceBookStatus::DRAFT) {
            throw new DomainException('Prices may only be defined in a draft PriceBook.');
        }

        return $book->prices()->updateOrCreate(
            ['plan_version_id' => $version->id, 'billing_interval' => $interval->value],
            [
                'currency' => $book->market->currency, 'amount_minor' => $amountMinor,
                'status' => PriceStatus::ACTIVE, 'tax_behavior' => $taxBehavior,
                'effective_from' => $effectiveFrom, 'effective_until' => $effectiveUntil,
            ],
        );
    }

    public function publish(PriceBook $book): PriceBook
    {
        return DB::transaction(function () use ($book): PriceBook {
            PricingMarket::query()->whereKey($book->pricing_market_id)->lockForUpdate()->firstOrFail();
            $locked = PriceBook::query()->lockForUpdate()->findOrFail($book->id);
            if ($locked->status !== PriceBookStatus::DRAFT || ! $locked->prices()->exists()) {
                throw new DomainException('Only a populated draft PriceBook may be published.');
            }

            $overlap = PriceBook::query()
                ->where('pricing_market_id', $locked->pricing_market_id)
                ->where('status', PriceBookStatus::ACTIVE->value)
                ->whereKeyNot($locked->id)
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $locked->effective_from));

            if ($locked->effective_until !== null) {
                $overlap->where('effective_from', '<', $locked->effective_until);
            }
            if ($overlap->exists()) {
                throw new DomainException('An active PriceBook overlaps this market and effective period.');
            }

            $locked->forceFill(['status' => PriceBookStatus::ACTIVE, 'published_at' => now()])->save();

            return $locked->fresh(['market', 'prices']);
        });
    }

    public function retire(PriceBook $book, ?\DateTimeInterface $effectiveUntil = null): PriceBook
    {
        $book->forceFill([
            'status' => PriceBookStatus::RETIRED,
            'effective_until' => $effectiveUntil ?? now(),
        ])->save();

        return $book->fresh();
    }
}
