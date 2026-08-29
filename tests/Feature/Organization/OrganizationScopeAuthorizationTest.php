<?php

namespace Tests\Feature\Organization;

use App\Enums\ChurchRole;
use App\Enums\OrganizationCapability;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationUnitStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\CongregationMember;
use App\Models\ContentItem;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitType;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Organizations\AuthorizedChurchAssignmentAction;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationIdentityService;
use App\Organizations\OrganizationScopeResolver;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizationScopeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationHierarchyService $hierarchy;

    private OrganizationIdentityService $identity;

    private Organization $organization;

    private OrganizationUnitType $type;

    /** @var array<string, OrganizationUnit> */
    private array $units;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hierarchy = app(OrganizationHierarchyService::class);
        $this->identity = app(OrganizationIdentityService::class);
        $this->organization = $this->hierarchy->createOrganization('Example International', 'example');
        $this->type = OrganizationUnitType::create([
            'organization_id' => $this->organization->id,
            'code' => 'region',
            'label' => 'Region',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $root = $this->organization->rootUnit;
        $nigeria = $this->unit($root, 'Nigeria', 'ng');
        $southSouth = $this->unit($nigeria, 'South-South', 'ss');
        $rivers = $this->unit($southSouth, 'Rivers', 'rivers');
        $portHarcourt = $this->unit($rivers, 'Port Harcourt', 'ph');
        $uk = $this->unit($root, 'United Kingdom', 'uk');
        $london = $this->unit($uk, 'London', 'london');
        $this->units = compact('root', 'nigeria', 'southSouth', 'rivers', 'portHarcourt', 'uk', 'london');
    }

    public function test_role_mapping_is_fixed_and_contains_no_church_or_care_capability(): void
    {
        $this->assertSame(OrganizationCapability::cases(), OrganizationRole::ORGANIZATION_ADMINISTRATOR->capabilities());
        $this->assertSame([
            OrganizationCapability::UnitsView,
            OrganizationCapability::UnitsManage,
            OrganizationCapability::ChurchesView,
            OrganizationCapability::ChurchesManageAssignments,
        ], OrganizationRole::UNIT_ADMINISTRATOR->capabilities());
        $this->assertSame([
            OrganizationCapability::OrganizationView,
            OrganizationCapability::UnitsView,
            OrganizationCapability::ChurchesView,
        ], OrganizationRole::ORGANIZATION_VIEWER->capabilities());
        $this->assertFalse(enum_exists('App\\Enums\\OrganizationCareCapability'));
        $this->assertFalse(in_array(ChurchRole::CARE->value, array_map(fn ($capability) => $capability->value, OrganizationCapability::cases()), true));
    }

    public function test_root_administrator_scope_contains_all_active_units_and_current_churches(): void
    {
        [$user] = $this->organizationUser(OrganizationRole::ORGANIZATION_ADMINISTRATOR, $this->units['root'], bootstrap: true);
        $riversChurch = $this->assignedChurch($this->units['rivers']);
        $londonChurch = $this->assignedChurch($this->units['london']);
        $this->actingAs($user);

        $resolver = app(OrganizationScopeResolver::class);
        $this->assertEqualsCanonicalizing(array_column($this->units, 'id'), $resolver->unitsInScope()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$riversChurch->id, $londonChurch->id], $resolver->churchesInScope()->pluck('id')->all());
        $this->assertTrue($resolver->canManageUnit($this->units['london']));
    }

    public function test_descendant_scope_and_sibling_denial_are_sql_backed(): void
    {
        [$user, $membership] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->units['southSouth']);
        $riversChurch = $this->assignedChurch($this->units['rivers']);
        $this->assignedChurch($this->units['london']);
        $this->actingAs($user);
        $resolver = app(OrganizationScopeResolver::class);

        $this->assertTrue($membership->hasCapability(OrganizationCapability::UnitsManage));
        $this->assertTrue($resolver->canManageUnit($this->units['southSouth']));
        $this->assertTrue($resolver->canManageUnit($this->units['rivers']));
        $this->assertFalse($resolver->canManageUnit($this->units['nigeria']));
        $this->assertFalse($resolver->canManageUnit($this->units['london']));
        $this->assertTrue($resolver->canManageChurchAssignment($riversChurch));
        $this->assertSame([$riversChurch->id], $resolver->churchesInScope(OrganizationCapability::ChurchesManageAssignments)->pluck('id')->all());
        $this->assertStringContainsString('exists', strtolower($resolver->unitsInScope()->toSql()));
    }

    public function test_sqlite_query_plan_uses_scope_indexes_without_materializing_ids_in_php(): void
    {
        [$user] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->units['southSouth']);
        $this->assignedChurch($this->units['rivers']);
        $this->actingAs($user);
        $resolver = app(OrganizationScopeResolver::class);

        $unitQuery = $resolver->unitsInScope();
        $unitPlan = collect(DB::select('EXPLAIN QUERY PLAN '.$unitQuery->toSql(), $unitQuery->getBindings()))
            ->pluck('detail')->implode(' ');
        $churchQuery = $resolver->churchesInScope();
        $churchPlan = collect(DB::select('EXPLAIN QUERY PLAN '.$churchQuery->toSql(), $churchQuery->getBindings()))
            ->pluck('detail')->implode(' ');

        $this->assertStringContainsString('org_role_assignments_membership_status_role_index', $unitPlan);
        $this->assertStringContainsString('sqlite_autoindex_organization_unit_paths_1', $unitPlan);
        $this->assertStringContainsString('church_organization_assignments_organization_id_status_requested_at_index', $churchPlan);
        $this->assertStringContainsString('SEARCH churches USING INTEGER PRIMARY KEY', $churchPlan);
        $this->assertStringContainsString('org_role_assignments_membership_status_role_index', $churchPlan);
    }

    public function test_multiple_roles_compose_without_erasing_scope(): void
    {
        [$user, $membership] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->units['southSouth']);
        $this->identity->assignRole($membership, OrganizationRole::ORGANIZATION_VIEWER, $this->units['uk']);
        $this->actingAs($user);
        $resolver = app(OrganizationScopeResolver::class);

        $this->assertTrue($resolver->canManageUnit($this->units['rivers']));
        $this->assertTrue($resolver->canViewUnit($this->units['london']));
        $this->assertFalse($resolver->canManageUnit($this->units['london']));
        $this->assertFalse($user->can('move', [$this->units['southSouth'], $this->units['rivers']]));
        $this->assertFalse($user->can('move', [$this->units['southSouth'], $this->units['uk']]));
    }

    public function test_archived_scope_cannot_authorize_mutation_and_historical_assignment_is_not_current(): void
    {
        [$user, $membership] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->units['southSouth']);
        $historicalChurch = $this->assignedChurch($this->units['rivers']);
        $activeAssignment = $historicalChurch->currentOrganizationAssignment;
        $this->hierarchy->detachChurch($historicalChurch);
        $this->units['southSouth']->forceFill(['status' => OrganizationUnitStatus::ARCHIVED])->save();
        $this->actingAs($user);
        app(OrganizationContext::class)->forgetResolved();

        $resolver = app(OrganizationScopeResolver::class);
        $this->assertTrue($membership->hasCapability(OrganizationCapability::UnitsManage));
        $this->assertFalse($resolver->canManageUnit($this->units['rivers']));
        $this->assertFalse($resolver->canViewChurch($historicalChurch));
        $this->assertNotNull($activeAssignment->fresh()->ended_at);
    }

    public function test_wrong_organization_and_direct_policy_access_fail_closed(): void
    {
        [$user] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->units['southSouth']);
        $other = $this->hierarchy->createOrganization('Other', 'other');
        $this->actingAs($user);

        $this->assertFalse($user->can('view', $other));
        $this->assertFalse($user->can('view', $other->rootUnit));
        $this->assertFalse($user->can('update', $this->organization));
        $this->assertTrue($user->can('view', $this->units['rivers']));
        $this->assertFalse($user->can('view', $this->units['london']));
    }

    public function test_only_root_organization_administrator_can_manage_memberships(): void
    {
        [$rootAdmin] = $this->organizationUser(OrganizationRole::ORGANIZATION_ADMINISTRATOR, $this->units['root'], bootstrap: true);
        [$unitAdmin, $unitMembership] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->units['southSouth']);

        $this->actingAs($rootAdmin);
        app(OrganizationContext::class)->forgetResolved();
        $this->assertTrue($rootAdmin->can('view', $unitMembership));
        $this->assertTrue($rootAdmin->can('update', $unitMembership));

        $this->actingAs($unitAdmin);
        app(OrganizationContext::class)->forgetResolved();
        $this->assertFalse($unitAdmin->can('view', $unitMembership));
        $this->assertFalse($unitAdmin->can('update', $unitMembership));
    }

    public function test_authorized_attachment_action_respects_destination_scope_and_preserves_primary_acceptance(): void
    {
        [$unitAdmin] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->units['southSouth']);
        $church = Church::factory()->create();
        $primary = User::factory()->create();
        ChurchMembership::createPrimary($church, $primary, [ChurchRole::ADMINISTRATOR]);
        $this->actingAs($unitAdmin);
        $action = app(AuthorizedChurchAssignmentAction::class);

        $pending = $action->request($unitAdmin, $this->organization, $this->units['rivers'], $church);
        $this->assertNull($church->fresh()->current_organization_assignment_id);
        $this->assertFalse($unitAdmin->can('acceptAttachment', $pending));
        $this->hierarchy->acceptAttachment($pending, $primary);
        $this->assertSame($pending->id, $church->fresh()->current_organization_assignment_id);
        $this->assertTrue(app(OrganizationScopeResolver::class)->canManageChurchAssignment($church));

        $moved = $action->move($unitAdmin, $pending->fresh(), $this->units['portHarcourt']);
        $this->assertSame($this->units['portHarcourt']->id, $moved->organization_unit_id);
        $action->detach($unitAdmin, $moved, 'Governance ended');
        $this->assertNull($church->fresh()->current_organization_assignment_id);

        $otherChurch = Church::factory()->create();
        $this->expectException(AuthorizationException::class);
        $action->request($unitAdmin, $this->organization, $this->units['london'], $otherChurch);
    }

    public function test_organization_authority_cannot_establish_tenant_or_query_operational_data(): void
    {
        [$organizationAdmin] = $this->organizationUser(OrganizationRole::ORGANIZATION_ADMINISTRATOR, $this->units['root'], bootstrap: true);
        $church = $this->assignedChurch($this->units['rivers']);
        $this->seedTenantOperationalRecords($church);
        $this->actingAs($organizationAdmin);

        $this->assertTrue(app(OrganizationScopeResolver::class)->canViewChurch($church));
        $this->assertFalse(app(TenantContext::class)->hasContext());
        $this->assertSame(0, CongregationMember::count());
        $this->assertSame(0, PrayerRequest::count());
        $this->assertSame(0, ContentItem::count());
        $this->assertFalse($organizationAdmin->can('viewAny', PrayerRequest::class));
        $this->assertSame(0, ChurchMembership::where('user_id', $organizationAdmin->id)->count());
    }

    public function test_separate_church_care_membership_grants_care_only_in_its_active_tenant(): void
    {
        [$organizationAdmin] = $this->organizationUser(OrganizationRole::ORGANIZATION_ADMINISTRATOR, $this->units['root'], bootstrap: true);
        $churchA = $this->assignedChurch($this->units['rivers']);
        $churchB = $this->assignedChurch($this->units['london']);
        ChurchMembership::create([
            'church_id' => $churchA->id,
            'user_id' => $organizationAdmin->id,
            'status' => 'active',
            'is_primary' => false,
            'joined_at' => now(),
        ])->assignRoles([ChurchRole::CARE]);
        $this->seedTenantOperationalRecords($churchA);
        $this->seedTenantOperationalRecords($churchB);
        $this->actingAs($organizationAdmin);

        $this->assertSame($churchA->id, app(TenantContext::class)->currentChurchId());
        $this->assertSame(1, PrayerRequest::count());
        $visible = PrayerRequest::first();
        $this->assertTrue($organizationAdmin->can('view', $visible));
    }

    private function unit(OrganizationUnit $parent, string $name, string $code): OrganizationUnit
    {
        return $this->hierarchy->createUnit($this->organization, $this->type, $parent, compact('name', 'code'));
    }

    /** @return array{User, OrganizationMembership} */
    private function organizationUser(OrganizationRole $role, OrganizationUnit $unit, bool $bootstrap = false): array
    {
        $user = User::factory()->create();
        if ($bootstrap) {
            $membership = $this->identity->bootstrapAdministrator($this->organization, $user);
        } else {
            $membership = $this->identity->invite($this->organization, $user);
            $membership = $this->identity->activate($membership);
            $this->identity->assignRole($membership, $role, $unit);
        }

        return [$user, $membership];
    }

    private function assignedChurch(OrganizationUnit $unit): Church
    {
        $church = Church::factory()->create();
        $primary = User::factory()->create();
        ChurchMembership::createPrimary($church, $primary, [ChurchRole::ADMINISTRATOR]);
        $pending = $this->hierarchy->attachChurch($this->organization, $unit, $church);
        $this->hierarchy->acceptAttachment($pending, $primary);

        return $church->fresh();
    }

    private function seedTenantOperationalRecords(Church $church): void
    {
        $member = new CongregationMember(['first_name' => 'Private', 'last_name' => 'Member', 'phone' => '+2348000000000']);
        $member->forceFill(['church_id' => $church->id])->save();
        $prayer = new PrayerRequest(['request' => 'Private care request']);
        $prayer->forceFill(['church_id' => $church->id])->save();
        $content = new ContentItem(['title' => 'Private content', 'content_type' => 'announcement', 'body' => 'Private body']);
        $content->forceFill(['church_id' => $church->id])->save();
    }
}
