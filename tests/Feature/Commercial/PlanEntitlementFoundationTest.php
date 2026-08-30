<?php

namespace Tests\Feature\Commercial;

use App\Commercial\Catalog\CommercialCatalogBootstrapper;
use App\Commercial\Catalog\CommercialCatalogService;
use App\Commercial\Entitlements\EntitlementResolver;
use App\Commercial\Entitlements\EntitlementValue;
use App\Commercial\Entitlements\ProductEntitlementSource;
use App\Enums\EntitlementDecisionReason;
use App\Enums\EntitlementKey;
use App\Enums\MarketplaceAccessType;
use App\Enums\MarketplaceAcquisitionBasis;
use App\Enums\PlanVersionStatus;
use App\Marketplace\Entitlements\DefaultMarketplaceEntitlement;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\MarketplaceItem;
use App\Models\OrganizationUnitType;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\User;
use App\Organizations\OrganizationHierarchyService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanEntitlementFoundationTest extends TestCase
{
    use RefreshDatabase;

    private CommercialCatalogService $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = app(CommercialCatalogService::class);
    }

    public function test_initial_catalogue_is_exact_and_bootstrap_is_idempotent(): void
    {
        $first = app(CommercialCatalogBootstrapper::class)->bootstrap();
        $second = app(CommercialCatalogBootstrapper::class)->bootstrap();

        $this->assertSame($first->id, $second->id);
        $this->assertSame('keryon', $first->plan->slug);
        $this->assertSame('Keryon', $first->plan->name);
        $this->assertSame('keryon-2026-1', $first->version_code);
        $this->assertSame(PlanVersionStatus::ACTIVE, $first->status);
        $this->assertNotNull($first->published_at);
        $this->assertSame(1, Plan::count());
        $this->assertSame(1, PlanVersion::count());
        $this->assertSame([
            EntitlementKey::DesignEnabled->value => true,
            EntitlementKey::FaithFlowEnabled->value => true,
            EntitlementKey::MarketplacePremiumEnabled->value => false,
            EntitlementKey::WebsiteEnabled->value => true,
        ], $first->entitlements->mapWithKeys(
            fn ($entitlement): array => [$entitlement->entitlement_key->value => $entitlement->value()->booleanValue()],
        )->sortKeys()->all());
    }

    public function test_bootstrap_command_is_idempotent(): void
    {
        $this->artisan('commercial:bootstrap-catalog')->assertSuccessful();
        $this->artisan('commercial:bootstrap-catalog')->assertSuccessful();

        $this->assertSame(1, Plan::count());
        $this->assertSame(1, PlanVersion::count());
        $this->assertDatabaseCount('plan_version_entitlements', 4);
    }

    public function test_catalogue_uniqueness_and_typed_values_are_enforced(): void
    {
        $plan = $this->catalog->createPlan('keryon', 'Keryon');
        $version = $this->catalog->createVersion($plan, 'keryon-2026-1');
        $definition = $this->catalog->defineEntitlement(
            $version,
            EntitlementKey::WebsiteEnabled,
            EntitlementValue::boolean(true),
        );

        $this->assertTrue($definition->fresh()->value()->booleanValue());
        $this->assertSame('boolean', $definition->value_type->value);

        $this->expectException(DomainException::class);
        $this->catalog->defineEntitlement(
            $version,
            EntitlementKey::WebsiteEnabled,
            EntitlementValue::integer(10),
        );
    }

    public function test_database_identity_constraints_are_unique(): void
    {
        $plan = $this->catalog->createPlan('keryon', 'Keryon');
        $this->catalog->createVersion($plan, 'keryon-2026-1');

        try {
            $this->catalog->createVersion($plan, 'keryon-2026-1');
            $this->fail('Duplicate PlanVersion identity was accepted.');
        } catch (QueryException) {
            $this->assertSame(1, $plan->versions()->count());
        }

        $this->expectException(QueryException::class);
        $this->catalog->createPlan('keryon', 'Duplicate');
    }

    public function test_published_definition_is_immutable_and_new_version_does_not_mutate_history(): void
    {
        $published = app(CommercialCatalogBootstrapper::class)->bootstrap();
        $original = $published->entitlements()
            ->where('entitlement_key', EntitlementKey::DesignEnabled->value)
            ->firstOrFail();

        try {
            $published->forceFill(['version_code' => 'changed'])->save();
            $this->fail('Published PlanVersion identity changed.');
        } catch (DomainException) {
            $this->assertSame('keryon-2026-1', $published->fresh()->version_code);
            $published = $published->fresh();
        }

        try {
            $original->forceFill(['boolean_value' => false])->save();
            $this->fail('Published entitlement changed.');
        } catch (DomainException) {
            $this->assertTrue($original->fresh()->value()->booleanValue());
        }

        try {
            $published->delete();
            $this->fail('Published PlanVersion was deleted.');
        } catch (DomainException) {
            $this->assertDatabaseHas('plan_versions', ['id' => $published->id]);
        }

        $next = $this->catalog->createVersion($published->plan, 'keryon-2027-1');
        $this->catalog->defineEntitlement($next, EntitlementKey::DesignEnabled, EntitlementValue::boolean(false));
        $this->catalog->publish($next);
        $this->catalog->retire($published);

        $this->assertSame(PlanVersionStatus::RETIRED, $published->fresh()->status);
        $this->assertTrue($original->fresh()->value()->booleanValue());
        $this->assertFalse($next->entitlements()->firstOrFail()->value()->booleanValue());
    }

    public function test_resolver_returns_rich_fail_closed_decisions(): void
    {
        $church = Church::factory()->create();
        $resolver = app(EntitlementResolver::class);

        $unassigned = $resolver->decision($church, EntitlementKey::WebsiteEnabled);
        $this->assertFalse($unassigned->allows());
        $this->assertSame(EntitlementDecisionReason::NO_PRODUCT_ASSIGNMENT, $unassigned->reason);
        $this->assertNull($unassigned->planVersionId);

        $version = app(CommercialCatalogBootstrapper::class)->bootstrap();
        $allowed = $resolver->decision($church, EntitlementKey::WebsiteEnabled);
        $disabled = $resolver->decision($church, EntitlementKey::MarketplacePremiumEnabled);

        $this->assertTrue($allowed->allows());
        $this->assertSame(EntitlementDecisionReason::ALLOWED, $allowed->reason);
        $this->assertSame($version->id, $allowed->planVersionId);
        $this->assertSame('current_product_default', $allowed->source);
        $this->assertTrue($allowed->value?->booleanValue());
        $this->assertFalse($disabled->allows());
        $this->assertSame(EntitlementDecisionReason::ENTITLEMENT_DISABLED, $disabled->reason);
        $this->assertFalse($disabled->value?->booleanValue());
    }

    public function test_missing_and_draft_entitlements_fail_closed_while_retired_version_remains_readable(): void
    {
        $church = Church::factory()->create();
        $plan = $this->catalog->createPlan('test', 'Test');
        $draft = $this->catalog->createVersion($plan, 'test-1');
        $source = $this->sourceReturning($draft);
        $resolver = new EntitlementResolver($source);

        $this->assertSame(
            EntitlementDecisionReason::PLAN_VERSION_INACTIVE,
            $resolver->decision($church, EntitlementKey::WebsiteEnabled)->reason,
        );

        $this->catalog->defineEntitlement($draft, EntitlementKey::WebsiteEnabled, EntitlementValue::boolean(true));
        $active = $this->catalog->publish($draft);
        $this->assertSame(
            EntitlementDecisionReason::ENTITLEMENT_MISSING,
            $resolver->decision($church, EntitlementKey::DesignEnabled)->reason,
        );

        $this->catalog->retire($active);
        $this->assertTrue($resolver->decision($church, EntitlementKey::WebsiteEnabled)->allows());
    }

    public function test_resolution_is_church_specific_and_organization_governance_does_not_change_it(): void
    {
        $plan = $this->catalog->createPlan('test', 'Test');
        $allowedVersion = $this->catalog->createVersion($plan, 'allowed');
        $this->catalog->defineEntitlement($allowedVersion, EntitlementKey::WebsiteEnabled, EntitlementValue::boolean(true));
        $allowedVersion = $this->catalog->publish($allowedVersion);
        $deniedVersion = $this->catalog->createVersion($plan, 'denied');
        $this->catalog->defineEntitlement($deniedVersion, EntitlementKey::WebsiteEnabled, EntitlementValue::boolean(false));
        $deniedVersion = $this->catalog->publish($deniedVersion);

        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $resolver = new EntitlementResolver(new class($churchA->id, $allowedVersion, $deniedVersion) implements ProductEntitlementSource
        {
            public function __construct(
                private readonly int $allowedChurchId,
                private readonly PlanVersion $allowed,
                private readonly PlanVersion $denied,
            ) {}

            public function planVersionFor(Church $church): ?PlanVersion
            {
                return $church->id === $this->allowedChurchId ? $this->allowed : $this->denied;
            }

            public function identifier(): string
            {
                return 'test_church_assignment';
            }
        });

        $this->assertTrue($resolver->allows($churchA, EntitlementKey::WebsiteEnabled));
        $this->assertFalse($resolver->allows($churchB, EntitlementKey::WebsiteEnabled));

        $hierarchy = app(OrganizationHierarchyService::class);
        $organization = $hierarchy->createOrganization('Example', 'example');
        $type = OrganizationUnitType::create([
            'organization_id' => $organization->id,
            'code' => 'region',
            'label' => 'Region',
            'is_active' => true,
        ]);
        $region = $hierarchy->createUnit($organization, $type, $organization->rootUnit, ['name' => 'Region', 'code' => 'region']);
        $otherRegion = $hierarchy->createUnit($organization, $type, $organization->rootUnit, ['name' => 'Other Region', 'code' => 'other-region']);
        $primary = User::factory()->create();
        ChurchMembership::createPrimary($churchA, $primary);
        $assignment = $hierarchy->attachChurch($organization, $region, $churchA);
        $hierarchy->acceptAttachment($assignment, $primary);

        $this->assertTrue($resolver->allows($churchA->fresh(), EntitlementKey::WebsiteEnabled));
        $hierarchy->moveChurch($churchA->fresh(), $otherRegion);
        $this->assertTrue($resolver->allows($churchA->fresh(), EntitlementKey::WebsiteEnabled));
        $hierarchy->detachChurch($churchA->fresh());
        $this->assertTrue($resolver->allows($churchA->fresh(), EntitlementKey::WebsiteEnabled));
    }

    public function test_marketplace_free_is_independent_and_premium_uses_canonical_entitlement(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create();
        $membership = ChurchMembership::createPrimary($church, $user);
        $adapter = app(DefaultMarketplaceEntitlement::class);
        $free = new MarketplaceItem(['access_type' => MarketplaceAccessType::FREE]);
        $premium = new MarketplaceItem(['access_type' => MarketplaceAccessType::PREMIUM]);

        $freeDecision = $adapter->decide($free, $membership);
        $this->assertTrue($freeDecision->allowed);
        $this->assertSame(MarketplaceAcquisitionBasis::FREE_INCLUDED, $freeDecision->basis);
        $this->assertFalse($adapter->decide($premium, $membership)->allowed);

        $plan = $this->catalog->createPlan('premium-test', 'Premium Test');
        $version = $this->catalog->createVersion($plan, 'premium-test-1');
        $this->catalog->defineEntitlement($version, EntitlementKey::MarketplacePremiumEnabled, EntitlementValue::boolean(true));
        $this->catalog->publish($version);
        config()->set('commercial.current_product.plan_slug', 'premium-test');
        config()->set('commercial.current_product.plan_version', 'premium-test-1');

        $premiumDecision = $adapter->decide($premium, $membership);
        $this->assertTrue($premiumDecision->allowed);
        $this->assertSame(MarketplaceAcquisitionBasis::PREMIUM_ENTITLEMENT, $premiumDecision->basis);
        $this->assertStringContainsString((string) $version->id, $premiumDecision->reference);
    }

    private function sourceReturning(PlanVersion $version): ProductEntitlementSource
    {
        return new class($version) implements ProductEntitlementSource
        {
            public function __construct(private readonly PlanVersion $version) {}

            public function planVersionFor(Church $church): ?PlanVersion
            {
                return $this->version->fresh();
            }

            public function identifier(): string
            {
                return 'test';
            }
        };
    }
}
