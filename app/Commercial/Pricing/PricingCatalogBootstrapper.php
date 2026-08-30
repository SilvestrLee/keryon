<?php

namespace App\Commercial\Pricing;

use App\Commercial\Catalog\CommercialCatalogBootstrapper;
use App\Enums\BillingInterval;
use App\Enums\PriceBookStatus;
use App\Models\PriceBook;
use App\Models\PricingMarket;
use DomainException;
use Illuminate\Support\Facades\DB;

class PricingCatalogBootstrapper
{
    public function __construct(
        private readonly PricingCatalogService $pricing,
        private readonly CommercialCatalogBootstrapper $products,
    ) {}

    /** @return array<string, PriceBook> */
    public function bootstrap(): array
    {
        return DB::transaction(function (): array {
            $version = $this->products->bootstrap();
            $definitions = [
                'NG' => ['name' => 'Nigeria', 'currency' => 'NGN', 'book' => 'nigeria-2026', 'book_name' => 'Nigeria 2026', 'monthly' => 2_500_000, 'annual' => 25_000_000],
                'US' => ['name' => 'United States', 'currency' => 'USD', 'book' => 'united-states-2026', 'book_name' => 'United States 2026', 'monthly' => 5_900, 'annual' => 59_000],
            ];
            $books = [];

            foreach ($definitions as $code => $definition) {
                $market = PricingMarket::query()->firstOrCreate(
                    ['code' => $code],
                    ['name' => $definition['name'], 'currency' => $definition['currency'], 'status' => 'active'],
                );
                if ($market->name !== $definition['name'] || $market->currency !== $definition['currency'] || $market->status->value !== 'active') {
                    throw new DomainException("Existing {$code} pricing market conflicts with the governed definition.");
                }
                $mapping = $market->countries()->firstOrCreate(['country_code' => $code]);
                if ($mapping->pricing_market_id !== $market->id) {
                    throw new DomainException("Existing {$code} country mapping conflicts with the governed definition.");
                }

                $book = PriceBook::query()->firstOrCreate(
                    ['pricing_market_id' => $market->id, 'code' => $definition['book']],
                    [
                        'name' => $definition['book_name'], 'status' => PriceBookStatus::DRAFT,
                        'effective_from' => '2026-01-01 00:00:00',
                    ],
                );
                if ($book->status === PriceBookStatus::DRAFT) {
                    $this->pricing->definePrice($book, $version, BillingInterval::MONTHLY, $definition['monthly']);
                    $this->pricing->definePrice($book, $version, BillingInterval::ANNUAL, $definition['annual']);
                    $book = $this->pricing->publish($book);
                }

                $actual = $book->prices()->where('plan_version_id', $version->id)
                    ->pluck('amount_minor', 'billing_interval')->map(fn ($amount) => (int) $amount)->sortKeys()->all();
                if ($book->name !== $definition['book_name']
                    || $book->effective_from?->format('Y-m-d') !== '2026-01-01'
                    || $actual !== ['annual' => $definition['annual'], 'monthly' => $definition['monthly']]) {
                    throw new DomainException("Existing {$code} PriceBook conflicts with the governed definition.");
                }
                $books[$code] = $book->fresh(['market', 'prices']);
            }

            return $books;
        });
    }
}
