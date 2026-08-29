<?php

namespace Tests\Feature\Organization;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Filament\Organization\Pages\OrganizationChurches;
use App\Filament\Organization\Pages\OrganizationOverview;
use App\Filament\Organization\Pages\OrganizationStaff;
use App\Filament\Organization\Pages\OrganizationUnits;
use App\Filament\Organization\Pages\SelectOrganization;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitType;
use App\Models\User;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationIdentityService;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationHierarchyService $hierarchy;

    private OrganizationIdentityService $identity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hierarchy = app(OrganizationHierarchyService::class);
        $this->identity = app(OrganizationIdentityService::class);
        Filament::setCurrentPanel(Filament::getPanel('organization'));
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        parent::tearDown();
    }

    public function test_panel_requires_active_organization_authority_and_never_establishes_tenant_context(): void
    {
        $churchOnly = User::factory()->create();
        $church = Church::factory()->create();
        ChurchMembership::createPrimary($church, $churchOnly);
        $this->actingAs($churchOnly)->get('/organization/organization-overview')->assertForbidden();

        $organization = $this->hierarchy->createOrganization('Example International', 'example');
        $member = User::factory()->create();
        $membership = $this->identity->bootstrapAdministrator($organization, $member);
        app(OrganizationContext::class)->forgetResolved();
        $response = $this->actingAs($member)->get('/organization/organization-overview');
        $this->assertSame(200, $response->status(), $response->getContent());
        $response
            ->assertSee('Organization workspace')
            ->assertDontSee('Prayer Requests');

        $this->assertFalse(app(TenantContext::class)->hasContext());
        $this->assertSame(1, ChurchMembership::count());

        $secondAdmin = User::factory()->create();
        $second = $this->identity->invite($organization, $secondAdmin);
        $this->identity->activate($second);
        $this->identity->assignRole($second, OrganizationRole::ORGANIZATION_ADMINISTRATOR, $organization->rootUnit);
        $this->identity->suspend($membership, $secondAdmin->id);
        app(OrganizationContext::class)->forgetResolved();
        $this->get('/organization/organization-overview')->assertForbidden();
    }

    public function test_multiple_organization_selection_is_validated_and_preserves_church_session(): void
    {
        $user = User::factory()->create();
        $first = $this->hierarchy->createOrganization('First Organization', 'first');
        $second = $this->hierarchy->createOrganization('Second Organization', 'second');
        $this->identity->bootstrapAdministrator($first, $user);
        $membership = OrganizationMembership::create([
            'organization_id' => $second->id,
            'user_id' => $user->id,
            'status' => OrganizationMembershipStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        $this->identity->assignRole($membership, OrganizationRole::ORGANIZATION_VIEWER, $second->rootUnit);
        $this->actingAs($user)->withSession(['active_church_id' => 9876]);

        $this->get('/organization/organization-overview')->assertForbidden();
        $response = $this->get('/organization/select-organization');
        $this->assertSame(200, $response->status(), $response->getContent());
        $response
            ->assertSee('First Organization')
            ->assertSee('Second Organization');

        Livewire::test(SelectOrganization::class)
            ->call('selectOrganization', 999999)
            ->assertForbidden();

        Livewire::test(SelectOrganization::class)
            ->call('selectOrganization', $second->id)
            ->assertRedirect(OrganizationOverviewRoute::url());

        $this->assertSame($second->id, session('active_organization_id'));
        $this->assertSame(9876, session('active_church_id'));
        app(OrganizationContext::class)->forgetResolved();
        $this->assertSame($second->id, app(OrganizationContext::class)->currentOrganizationId());
    }

    public function test_unit_and_church_pages_are_scope_bounded_and_read_only_for_viewers(): void
    {
        [$organization, $type, $south, $rivers, $uk] = $this->structure();
        [$unitAdmin] = $this->member($organization, OrganizationRole::UNIT_ADMINISTRATOR, $south);
        $riversChurch = $this->assignedChurch($organization, $rivers, 'Port Harcourt Church');
        $this->assignedChurch($organization, $uk, 'London Church');
        $this->actingAs($unitAdmin);

        Livewire::test(OrganizationUnits::class)
            ->assertSee('South')
            ->assertSee('Rivers')
            ->assertDontSee('United Kingdom')
            ->assertActionVisible('createUnit');
        Livewire::test(OrganizationChurches::class)
            ->assertSee($riversChurch->name)
            ->assertDontSee('London Church');

        [$viewer] = $this->member($organization, OrganizationRole::ORGANIZATION_VIEWER, $south);
        $this->actingAs($viewer);
        app(OrganizationContext::class)->forgetResolved();
        Livewire::test(OrganizationUnits::class)->assertActionHidden('createUnit');
        $this->get('/organization/staff')->assertForbidden();
    }

    public function test_out_of_scope_mutation_payloads_fail_closed_without_moving_structure(): void
    {
        [$organization, $type, $south, $rivers, $uk] = $this->structure();
        [$unitAdmin] = $this->member($organization, OrganizationRole::UNIT_ADMINISTRATOR, $south);
        $this->actingAs($unitAdmin);
        $originalParent = $rivers->parent_id;

        Livewire::test(OrganizationUnits::class)
            ->callAction('moveUnit', data: ['destination_id' => $uk->id], arguments: ['unit' => $rivers->id]);

        $this->assertSame($originalParent, $rivers->fresh()->parent_id);
    }

    public function test_staff_page_is_root_governed_and_existing_identity_service_enforces_last_admin(): void
    {
        $organization = $this->hierarchy->createOrganization('Example International', 'example');
        $admin = User::factory()->create();
        $membership = $this->identity->bootstrapAdministrator($organization, $admin);
        $this->actingAs($admin);

        Livewire::test(OrganizationStaff::class)
            ->assertSee($admin->name)
            ->assertSee('Organization Administrator')
            ->callAction('removeMembership', arguments: ['membership' => $membership->id]);

        $this->assertSame(OrganizationMembershipStatus::ACTIVE, $membership->fresh()->status);
        $this->assertSame(0, ChurchMembership::where('user_id', $admin->id)->count());
    }

    public function test_inactive_organization_and_archived_units_fail_closed(): void
    {
        [$organization, $type, $south] = $this->structure();
        [$admin] = $this->member($organization, OrganizationRole::ORGANIZATION_ADMINISTRATOR, $organization->rootUnit, true);
        $south->forceFill(['status' => 'archived'])->save();
        $this->actingAs($admin);

        Livewire::test(OrganizationUnits::class)
            ->assertSee('Archived')
            ->assertDontSeeHtml("mountAction('archiveUnit', { unit: {$south->id} })");

        $organization->forceFill(['status' => OrganizationStatus::SUSPENDED])->save();
        app(OrganizationContext::class)->forgetResolved();
        $this->get('/organization/organization-overview')->assertForbidden();
    }

    /** @return array{Organization, OrganizationUnitType, OrganizationUnit, OrganizationUnit, OrganizationUnit} */
    private function structure(): array
    {
        $organization = $this->hierarchy->createOrganization('Example International', 'example');
        $type = OrganizationUnitType::create([
            'organization_id' => $organization->id,
            'code' => 'region',
            'label' => 'Region',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $south = $this->hierarchy->createUnit($organization, $type, $organization->rootUnit, ['name' => 'South', 'code' => 'south']);
        $rivers = $this->hierarchy->createUnit($organization, $type, $south, ['name' => 'Rivers', 'code' => 'rivers']);
        $uk = $this->hierarchy->createUnit($organization, $type, $organization->rootUnit, ['name' => 'United Kingdom', 'code' => 'uk']);

        return [$organization, $type, $south, $rivers, $uk];
    }

    /** @return array{User, OrganizationMembership} */
    private function member(Organization $organization, OrganizationRole $role, OrganizationUnit $unit, bool $bootstrap = false): array
    {
        $user = User::factory()->create();
        if ($bootstrap) {
            $membership = $this->identity->bootstrapAdministrator($organization, $user);
        } else {
            $membership = $this->identity->invite($organization, $user);
            $membership = $this->identity->activate($membership);
            $this->identity->assignRole($membership, $role, $unit);
        }

        return [$user, $membership];
    }

    private function assignedChurch(Organization $organization, OrganizationUnit $unit, string $name): Church
    {
        $church = Church::factory()->create(['name' => $name]);
        $primary = User::factory()->create();
        ChurchMembership::createPrimary($church, $primary);
        $pending = $this->hierarchy->attachChurch($organization, $unit, $church);
        $this->hierarchy->acceptAttachment($pending, $primary);

        return $church->fresh();
    }
}

/** Isolates the generated page URL call from static analysis ambiguity. */
final class OrganizationOverviewRoute
{
    public static function url(): string
    {
        return OrganizationOverview::getUrl();
    }
}
