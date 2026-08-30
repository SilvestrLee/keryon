<?php

namespace Tests\Feature\Commercial;

use App\Commercial\Catalog\CommercialCatalogBootstrapper;
use App\Commercial\Entitlements\EntitlementResolver;
use App\Commercial\Pricing\PricingCatalogBootstrapper;
use App\Commercial\Pricing\PricingCatalogService;
use App\Commercial\Pricing\PricingMarketResolver;
use App\Commercial\Pricing\PricingService;
use App\Enums\BillingInterval;
use App\Enums\EntitlementKey;
use App\Enums\PriceBookStatus;
use App\Enums\PricingDecisionReason;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\OrganizationMembership;
use App\Models\OrganizationUnitType;
use App\Models\PlanVersion;
use App\Models\Price;
use App\Models\PriceBook;
use App\Models\PricingMarket;
use App\Models\PricingMarketCountry;
use App\Models\User;
use App\Organizations\OrganizationHierarchyService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiMarketPricingFoundationTest extends TestCase
{
    use RefreshDatabase;

    private PricingCatalogService $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = app(PricingCatalogService::class);
    }

    public function test_bootstrap_is_exact_and_idempotent(): void
    {
        $first = app(PricingCatalogBootstrapper::class)->bootstrap();
        $second = app(PricingCatalogBootstrapper::class)->bootstrap();

        $this->assertSame($first['NG']->id, $second['NG']->id);
        $this->assertSame($first['US']->id, $second['US']->id);
        $this->assertSame(2, PricingMarket::count());
        $this->assertSame(2, PricingMarketCountry::count());
        $this->assertSame(2, PriceBook::count());
        $this->assertSame(4, Price::count());
        $this->assertSame(['NG' => 'NGN', 'US' => 'USD'], PricingMarket::query()->orderBy('code')->pluck('currency', 'code')->all());
        $this->assertSame([
            'NG-annual' => 25_000_000,
            'NG-monthly' => 2_500_000,
            'US-annual' => 59_000,
            'US-monthly' => 5_900,
        ], Price::query()->join('price_books', 'prices.price_book_id', '=', 'price_books.id')
            ->join('pricing_markets', 'price_books.pricing_market_id', '=', 'pricing_markets.id')
            ->orderBy('pricing_markets.code')->orderBy('prices.billing_interval')
            ->get(['pricing_markets.code', 'prices.billing_interval', 'prices.amount_minor'])
            ->mapWithKeys(fn ($row): array => ["{$row->code}-{$row->billing_interval->value}" => (int) $row->amount_minor])->all());
    }

    public function test_bootstrap_command_is_idempotent(): void
    {
        $this->artisan('commercial:bootstrap-pricing')->assertSuccessful();
        $this->artisan('commercial:bootstrap-pricing')->assertSuccessful();
        $this->assertDatabaseCount('pricing_markets', 2);
        $this->assertDatabaseCount('prices', 4);
    }

    public function test_country_mapping_is_deterministic_and_unknown_country_fails_closed(): void
    {
        app(PricingCatalogBootstrapper::class)->bootstrap();
        $resolver = app(PricingMarketResolver::class);

        $this->assertSame('NG', $resolver->forCountry('ng')->market?->code);
        $this->assertSame('US', $resolver->forCountry('US')->market?->code);
        $this->assertFalse($resolver->forCountry('GB')->resolved());
        $this->assertSame('country_unmapped', $resolver->forCountry('GB')->reason);
        $this->assertFalse($resolver->forCountry(null)->resolved());
        $this->assertNull(BillingInterval::tryFrom('weekly'));
    }

    public function test_same_plan_version_has_different_prices_and_identical_entitlements(): void
    {
        $books = app(PricingCatalogBootstrapper::class)->bootstrap();
        $version = PlanVersion::query()->where('version_code', 'keryon-2026-1')->firstOrFail();
        $pricing = app(PricingService::class);
        $churchNg = Church::factory()->create();
        $churchUs = Church::factory()->create();
        $entitlements = app(EntitlementResolver::class);

        $ngMonthly = $pricing->quote($version, $books['NG']->market, BillingInterval::MONTHLY);
        $ngAnnual = $pricing->quote($version, $books['NG']->market, BillingInterval::ANNUAL);
        $usMonthly = $pricing->quote($version, $books['US']->market, BillingInterval::MONTHLY);
        $usAnnual = $pricing->quote($version, $books['US']->market, BillingInterval::ANNUAL);

        $this->assertTrue($ngMonthly->resolved);
        $this->assertSame(['NGN', 2_500_000], [$ngMonthly->currency, $ngMonthly->amountMinor]);
        $this->assertSame(['NGN', 25_000_000], [$ngAnnual->currency, $ngAnnual->amountMinor]);
        $this->assertSame(['USD', 5_900], [$usMonthly->currency, $usMonthly->amountMinor]);
        $this->assertSame(['USD', 59_000], [$usAnnual->currency, $usAnnual->amountMinor]);
        $this->assertSame($version->id, $ngMonthly->planVersionId);
        $this->assertSame($version->id, $usMonthly->planVersionId);
        $this->assertTrue($entitlements->allows($churchNg, EntitlementKey::WebsiteEnabled));
        $this->assertTrue($entitlements->allows($churchUs, EntitlementKey::WebsiteEnabled));
        $this->assertFalse($entitlements->allows($churchNg, EntitlementKey::MarketplacePremiumEnabled));
        $this->assertFalse($entitlements->allows($churchUs, EntitlementKey::MarketplacePremiumEnabled));
    }

    public function test_ineligible_or_ineffective_catalogue_fails_closed(): void
    {
        $version = app(CommercialCatalogBootstrapper::class)->bootstrap();
        $pricing = app(PricingService::class);
        $market = $this->catalog->createMarket('NG', 'Nigeria', 'NGN');
        $future = $this->catalog->createPriceBook($market, 'future', 'Future', CarbonImmutable::parse('2030-01-01'));
        $this->catalog->definePrice($future, $version, BillingInterval::MONTHLY, 3_000_000);
        $this->catalog->publish($future);

        $decision = $pricing->quote($version, $market, BillingInterval::MONTHLY, CarbonImmutable::parse('2029-01-01'));
        $this->assertFalse($decision->resolved);
        $this->assertSame(PricingDecisionReason::PRICE_BOOK_UNAVAILABLE, $decision->reason);

        $this->catalog->retire($future, CarbonImmutable::parse('2031-01-01'));
        $expired = $pricing->quote($version, $market, BillingInterval::MONTHLY, CarbonImmutable::parse('2032-01-01'));
        $this->assertSame(PricingDecisionReason::PRICE_BOOK_UNAVAILABLE, $expired->reason);

        $market->update(['status' => 'inactive']);
        $this->assertSame(PricingDecisionReason::MARKET_INACTIVE, $pricing->quote($version, $market->fresh(), BillingInterval::MONTHLY)->reason);
    }

    public function test_overlapping_price_books_and_currency_mismatch_are_rejected(): void
    {
        $version = app(CommercialCatalogBootstrapper::class)->bootstrap();
        $market = $this->catalog->createMarket('US', 'United States', 'USD');
        $first = $this->catalog->createPriceBook($market, 'first', 'First', CarbonImmutable::parse('2026-01-01'));
        $this->catalog->definePrice($first, $version, BillingInterval::MONTHLY, 5_900);
        $this->catalog->publish($first);
        $second = $this->catalog->createPriceBook($market, 'second', 'Second', CarbonImmutable::parse('2027-01-01'));
        $this->catalog->definePrice($second, $version, BillingInterval::MONTHLY, 6_900);

        try {
            $this->catalog->publish($second);
            $this->fail('Overlapping PriceBook was published.');
        } catch (DomainException) {
            $this->assertSame(PriceBookStatus::DRAFT, $second->fresh()->status);
        }

        $third = $this->catalog->createPriceBook($market, 'third', 'Third', CarbonImmutable::parse('2031-01-01'));
        $this->expectException(DomainException::class);
        $third->prices()->create([
            'plan_version_id' => $version->id, 'billing_interval' => 'monthly',
            'currency' => 'NGN', 'amount_minor' => 1, 'status' => 'active', 'tax_behavior' => 'unspecified',
        ]);
    }

    public function test_price_history_is_reproducible_through_new_price_book(): void
    {
        $version = app(CommercialCatalogBootstrapper::class)->bootstrap();
        $market = $this->catalog->createMarket('NG', 'Nigeria', 'NGN');
        $old = $this->catalog->createPriceBook($market, 'ng-2026', 'Nigeria 2026', CarbonImmutable::parse('2026-01-01'));
        $oldPrice = $this->catalog->definePrice($old, $version, BillingInterval::MONTHLY, 2_500_000);
        $old = $this->catalog->publish($old);
        $this->catalog->retire($old, CarbonImmutable::parse('2027-01-01'));
        $new = $this->catalog->createPriceBook($market, 'ng-2027', 'Nigeria 2027', CarbonImmutable::parse('2027-01-01'));
        $this->catalog->definePrice($new, $version, BillingInterval::MONTHLY, 3_000_000);
        $this->catalog->publish($new);

        $pricing = app(PricingService::class);
        $historical = $pricing->quote($version, $market, BillingInterval::MONTHLY, CarbonImmutable::parse('2026-08-01'));
        $current = $pricing->quote($version, $market, BillingInterval::MONTHLY, CarbonImmutable::parse('2027-08-01'));
        $this->assertSame(2_500_000, $historical->amountMinor);
        $this->assertSame(3_000_000, $current->amountMinor);
        $this->assertSame(2_500_000, $oldPrice->fresh()->amount_minor);
    }

    public function test_organization_governance_does_not_create_or_change_pricing_identity(): void
    {
        app(PricingCatalogBootstrapper::class)->bootstrap();
        $resolver = app(PricingMarketResolver::class);
        $church = Church::factory()->create();
        $primary = User::factory()->create();
        ChurchMembership::createPrimary($church, $primary);
        $hierarchy = app(OrganizationHierarchyService::class);
        $organization = $hierarchy->createOrganization('Example', 'example');
        $type = OrganizationUnitType::create(['organization_id' => $organization->id, 'code' => 'region', 'label' => 'Region', 'is_active' => true]);
        $one = $hierarchy->createUnit($organization, $type, $organization->rootUnit, ['name' => 'One', 'code' => 'one']);
        $two = $hierarchy->createUnit($organization, $type, $organization->rootUnit, ['name' => 'Two', 'code' => 'two']);
        $before = $resolver->forCountry('NG')->market?->id;
        $assignment = $hierarchy->attachChurch($organization, $one, $church);
        $hierarchy->acceptAttachment($assignment, $primary);
        $hierarchy->moveChurch($church->fresh(), $two);
        $hierarchy->detachChurch($church->fresh());

        $this->assertSame($before, $resolver->forCountry('NG')->market?->id);
        $this->assertFalse((new OrganizationMembership)->isFillable('pricing_market_id'));
        $this->assertFalse($church->fresh()->isFillable('pricing_market_id'));
    }
}
