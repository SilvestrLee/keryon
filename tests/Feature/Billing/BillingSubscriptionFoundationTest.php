<?php

namespace Tests\Feature\Billing;

use App\Billing\BillingAccountService;
use App\Billing\SubscriptionService;
use App\Commercial\Catalog\CommercialCatalogService;
use App\Commercial\Entitlements\EntitlementResolver;
use App\Commercial\Entitlements\EntitlementValue;
use App\Commercial\Pricing\PricingCatalogBootstrapper;
use App\Commercial\Pricing\PricingCatalogService;
use App\Enums\BillingAccountOwnerType;
use App\Enums\BillingInterval;
use App\Enums\CommercialAuditEventType;
use App\Enums\EntitlementKey;
use App\Enums\SubscriptionStatus;
use App\Models\BillingAccount;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\OrganizationMembership;
use App\Models\OrganizationUnitType;
use App\Models\PlanVersion;
use App\Models\Subscription;
use App\Models\User;
use App\Organizations\OrganizationHierarchyService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSubscriptionFoundationTest extends TestCase
{
    use RefreshDatabase;

    private BillingAccountService $accounts;

    private SubscriptionService $subscriptions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = app(BillingAccountService::class);
        $this->subscriptions = app(SubscriptionService::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_bounded_billing_account_owners_are_valid_and_audited(): void
    {
        $church = Church::factory()->create();
        $hierarchy = app(OrganizationHierarchyService::class);
        $organization = $hierarchy->createOrganization('International', 'international');
        $type = OrganizationUnitType::create(['organization_id' => $organization->id, 'code' => 'region', 'label' => 'Region', 'is_active' => true]);
        $unit = $hierarchy->createUnit($organization, $type, $organization->rootUnit, ['name' => 'Nigeria', 'code' => 'ng']);

        $churchAccount = $this->accounts->create('Church payer', $church, null, 'test');
        $organizationAccount = $this->accounts->create('HQ payer', $organization, null, 'test');
        $unitAccount = $this->accounts->create('Region payer', $unit, null, 'test');

        $this->assertSame(BillingAccountOwnerType::CHURCH, $churchAccount->owner_type);
        $this->assertSame($church->id, $churchAccount->church_id);
        $this->assertSame(BillingAccountOwnerType::ORGANIZATION, $organizationAccount->owner_type);
        $this->assertSame($organization->id, $organizationAccount->organization_id);
        $this->assertSame(BillingAccountOwnerType::ORGANIZATION_UNIT, $unitAccount->owner_type);
        $this->assertSame($unit->id, $unitAccount->organization_unit_id);
        $this->assertDatabaseCount('commercial_audit_events', 3);
        $this->assertSame(0, ChurchMembership::count());
    }

    public function test_exactly_one_matching_owner_is_enforced_by_domain_model(): void
    {
        $church = Church::factory()->create();
        $organization = app(OrganizationHierarchyService::class)->createOrganization('International', 'international');

        $this->expectException(DomainException::class);
        BillingAccount::query()->create([
            'name' => 'Invalid', 'owner_type' => BillingAccountOwnerType::CHURCH,
            'church_id' => $church->id, 'organization_id' => $organization->id, 'status' => 'active',
        ]);
    }

    public function test_trial_is_explicit_21_days_and_entitles_until_expiry(): void
    {
        CarbonImmutable::setTestNow('2026-08-30 10:00:00');
        [$version, $market, $price] = $this->catalogue('NG', BillingInterval::MONTHLY);
        $church = Church::factory()->create();
        $primary = User::factory()->create();
        ChurchMembership::createPrimary($church, $primary);
        $payer = $this->accounts->create('Church payer', $church, $primary->id, 'assisted_activation');

        $trial = $this->subscriptions->startTrial($church, $payer, $market, $version, $price, $primary->id, 'assisted_activation');

        $this->assertSame(SubscriptionStatus::TRIALING, $trial->status);
        $this->assertSame('2026-09-20', $trial->trial_ends_at->format('Y-m-d'));
        $this->assertSame($version->id, $trial->item->plan_version_id);
        $this->assertSame($price->id, $trial->item->price_id);
        $this->assertSame($trial->id, $church->fresh()->current_subscription_id);
        $decision = app(EntitlementResolver::class)->decision($church->fresh(), EntitlementKey::WebsiteEnabled);
        $this->assertTrue($decision->allows());
        $this->assertSame('subscription', $decision->source);
        $this->assertFalse(app(EntitlementResolver::class)->allows($church->fresh(), EntitlementKey::MarketplacePremiumEnabled));

        CarbonImmutable::setTestNow('2026-09-21 10:00:00');
        $this->assertFalse(app(EntitlementResolver::class)->allows($church->fresh(), EntitlementKey::WebsiteEnabled));
        $this->assertDatabaseHas('churches', ['id' => $church->id]);
        $this->assertDatabaseHas('church_memberships', ['church_id' => $church->id, 'user_id' => $primary->id]);
    }

    public function test_active_subscription_alignment_and_one_effective_pointer_are_enforced(): void
    {
        [$version, $market, $price] = $this->catalogue('US', BillingInterval::ANNUAL);
        $church = Church::factory()->create();
        $payer = $this->accounts->create('Church payer', $church, null, 'test');
        $subscription = $this->subscriptions->activate($church, $payer, $market, $version, $price, null, 'test');

        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertSame($church->id, $subscription->church_id);
        $this->assertSame($market->id, $subscription->pricing_market_id);
        $this->assertSame($version->id, $subscription->item->plan_version_id);
        $this->assertSame($price->id, $subscription->item->price_id);

        $this->expectException(DomainException::class);
        $this->subscriptions->activate($church->fresh(), $payer, $market, $version, $price, null, 'duplicate');
    }

    public function test_unexpired_trial_can_activate_without_changing_commercial_identity(): void
    {
        CarbonImmutable::setTestNow('2026-08-30 10:00:00');
        [$version, $market, $price] = $this->catalogue('US', BillingInterval::MONTHLY);
        $church = Church::factory()->create();
        $payer = $this->accounts->create('Payer', $church, null, 'test');
        $trial = $this->subscriptions->startTrial($church, $payer, $market, $version, $price, null, 'test');

        $active = $this->subscriptions->activateTrial($trial, null, 'approved_conversion');

        $this->assertSame(SubscriptionStatus::ACTIVE, $active->status);
        $this->assertSame($church->id, $active->church_id);
        $this->assertSame($payer->id, $active->billing_account_id);
        $this->assertSame($market->id, $active->pricing_market_id);
        $this->assertSame($version->id, $active->item->plan_version_id);
        $this->assertSame($price->id, $active->item->price_id);
    }

    public function test_mismatched_price_market_or_plan_is_rejected(): void
    {
        $books = app(PricingCatalogBootstrapper::class)->bootstrap();
        $version = PlanVersion::query()->where('version_code', 'keryon-2026-1')->firstOrFail();
        $ngPrice = $books['NG']->prices()->where('billing_interval', 'monthly')->firstOrFail();
        $church = Church::factory()->create();
        $payer = $this->accounts->create('Church payer', $church, null, 'test');

        $this->expectException(DomainException::class);
        $this->subscriptions->activate($church, $payer, $books['US']->market, $version, $ngPrice, null, 'test');
    }

    public function test_database_effective_slot_rejects_a_second_current_base_subscription(): void
    {
        [$version, $market, $price] = $this->catalogue('US', BillingInterval::MONTHLY);
        $church = Church::factory()->create();
        $payer = $this->accounts->create('Payer', $church, null, 'test');
        $this->subscriptions->activate($church, $payer, $market, $version, $price, null, 'test');

        $this->expectException(QueryException::class);
        Subscription::query()->create([
            'church_id' => $church->id,
            'billing_account_id' => $payer->id,
            'pricing_market_id' => $market->id,
            'status' => SubscriptionStatus::ACTIVE,
            'effective_slot' => 1,
            'started_at' => now(),
        ]);
    }

    public function test_immediate_cancellation_preserves_history_and_blocks_fallback(): void
    {
        [$version, $market, $price] = $this->catalogue('NG', BillingInterval::MONTHLY);
        $church = Church::factory()->create();
        $payer = $this->accounts->create('Church payer', $church, null, 'test');
        $subscription = $this->subscriptions->activate($church, $payer, $market, $version, $price, null, 'test');

        $cancelled = $this->subscriptions->cancel($subscription, null, 'governed_cancellation');

        $this->assertSame(SubscriptionStatus::CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertNotNull($cancelled->ended_at);
        $this->assertNull($church->fresh()->current_subscription_id);
        $this->assertFalse(app(EntitlementResolver::class)->allows($church->fresh(), EntitlementKey::WebsiteEnabled));
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
        $this->assertDatabaseHas('subscription_items', ['subscription_id' => $subscription->id, 'price_id' => $price->id]);
    }

    public function test_transitional_fallback_only_applies_before_any_subscription_history(): void
    {
        app(PricingCatalogBootstrapper::class)->bootstrap();
        $legacyChurch = Church::factory()->create();
        $decision = app(EntitlementResolver::class)->decision($legacyChurch, EntitlementKey::WebsiteEnabled);
        $this->assertTrue($decision->allows());
        $this->assertSame('transitional_current_product', $decision->source);

        config()->set('commercial.transitional_subscription_fallback', false);
        $this->assertFalse(app(EntitlementResolver::class)->allows($legacyChurch, EntitlementKey::WebsiteEnabled));
    }

    public function test_payer_changes_preserve_recipient_product_price_and_market(): void
    {
        [$version, $market, $price] = $this->catalogue('NG', BillingInterval::MONTHLY);
        $church = Church::factory()->create();
        $churchPayer = $this->accounts->create('Church payer', $church, null, 'test');
        $organization = app(OrganizationHierarchyService::class)->createOrganization('HQ', 'hq');
        $hqPayer = $this->accounts->create('HQ payer', $organization, null, 'test');
        $subscription = $this->subscriptions->activate($church, $churchPayer, $market, $version, $price, null, 'test');

        $changed = $this->subscriptions->changePayer($subscription, $hqPayer, null, 'approved_hq_payer');

        $this->assertSame($church->id, $changed->church_id);
        $this->assertSame($hqPayer->id, $changed->billing_account_id);
        $this->assertSame($market->id, $changed->pricing_market_id);
        $this->assertSame($version->id, $changed->item->plan_version_id);
        $this->assertSame($price->id, $changed->item->price_id);
        $this->assertSame(0, ChurchMembership::count());
        $this->assertSame(0, OrganizationMembership::count());
        $this->assertDatabaseHas('commercial_audit_events', ['event_type' => CommercialAuditEventType::SUBSCRIPTION_PAYER_CHANGED->value]);
    }

    public function test_unit_payer_survives_hierarchy_move_and_organization_detach(): void
    {
        [$version, $market, $price] = $this->catalogue('NG', BillingInterval::MONTHLY);
        $church = Church::factory()->create();
        $primary = User::factory()->create();
        ChurchMembership::createPrimary($church, $primary);
        $hierarchy = app(OrganizationHierarchyService::class);
        $organization = $hierarchy->createOrganization('HQ', 'hq');
        $type = OrganizationUnitType::create(['organization_id' => $organization->id, 'code' => 'region', 'label' => 'Region', 'is_active' => true]);
        $one = $hierarchy->createUnit($organization, $type, $organization->rootUnit, ['name' => 'One', 'code' => 'one']);
        $two = $hierarchy->createUnit($organization, $type, $organization->rootUnit, ['name' => 'Two', 'code' => 'two']);
        $payer = $this->accounts->create('Region payer', $one, null, 'test');
        $subscription = $this->subscriptions->activate($church, $payer, $market, $version, $price, null, 'test');
        $assignment = $hierarchy->attachChurch($organization, $one, $church);
        $hierarchy->acceptAttachment($assignment, $primary);
        $hierarchy->moveChurch($church->fresh(), $two);
        $this->assertSame($payer->id, $subscription->fresh()->billing_account_id);
        $hierarchy->detachChurch($church->fresh());
        $this->assertSame($payer->id, $subscription->fresh()->billing_account_id);
        $this->assertTrue(app(EntitlementResolver::class)->allows($church->fresh(), EntitlementKey::WebsiteEnabled));
    }

    public function test_inactive_payer_is_rejected_and_effective_payer_cannot_be_deactivated(): void
    {
        [$version, $market, $price] = $this->catalogue('US', BillingInterval::MONTHLY);
        $church = Church::factory()->create();
        $payer = $this->accounts->create('Payer', $church, null, 'test');
        $this->accounts->deactivate($payer, null, 'test');

        try {
            $this->subscriptions->activate($church, $payer->fresh(), $market, $version, $price, null, 'test');
            $this->fail('Inactive payer accepted.');
        } catch (DomainException) {
            $this->assertSame(0, Subscription::count());
        }

        $active = $this->accounts->create('Active', $church, null, 'test');
        $this->subscriptions->activate($church, $active, $market, $version, $price, null, 'test');
        $this->expectException(DomainException::class);
        $this->accounts->deactivate($active, null, 'test');
    }

    public function test_plan_and_price_are_grandfathered_when_new_catalogue_versions_exist(): void
    {
        [$version, $market, $price] = $this->catalogue('NG', BillingInterval::MONTHLY);
        $church = Church::factory()->create();
        $payer = $this->accounts->create('Payer', $church, null, 'test');
        $subscription = $this->subscriptions->activate($church, $payer, $market, $version, $price, null, 'test');

        $products = app(CommercialCatalogService::class);
        $nextVersion = $products->createVersion($version->plan, 'keryon-2027-1');
        $products->defineEntitlement($nextVersion, EntitlementKey::WebsiteEnabled, EntitlementValue::boolean(false));
        $products->publish($nextVersion);
        $pricing = app(PricingCatalogService::class);
        $pricing->retire($price->priceBook, CarbonImmutable::parse('2027-01-01'));
        $nextBook = $pricing->createPriceBook($market, 'ng-2027', 'Nigeria 2027', CarbonImmutable::parse('2027-01-01'));
        $newPrice = $pricing->definePrice($nextBook, $nextVersion, BillingInterval::MONTHLY, 3_000_000);
        $pricing->publish($nextBook);

        $item = $subscription->item->fresh();
        $this->assertSame($version->id, $item->plan_version_id);
        $this->assertSame($price->id, $item->price_id);
        $this->assertNotSame($nextVersion->id, $item->plan_version_id);
        $this->assertNotSame($newPrice->id, $item->price_id);
        $this->assertTrue(app(EntitlementResolver::class)->allows($church->fresh(), EntitlementKey::WebsiteEnabled));
    }

    private function catalogue(string $marketCode, BillingInterval $interval): array
    {
        $books = app(PricingCatalogBootstrapper::class)->bootstrap();
        $version = PlanVersion::query()->where('version_code', 'keryon-2026-1')->firstOrFail();
        $book = $books[$marketCode];
        $price = $book->prices()->where('billing_interval', $interval->value)->firstOrFail();

        return [$version, $book->market, $price];
    }
}
