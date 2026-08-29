<?php

namespace Tests\Feature\Organization;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\ChurchMembership;
use App\Models\OrganizationAuditEvent;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationIdentityService;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OrganizationIdentityContextTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationHierarchyService $hierarchy;

    private OrganizationIdentityService $identity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hierarchy = app(OrganizationHierarchyService::class);
        $this->identity = app(OrganizationIdentityService::class);
    }

    public function test_zero_or_non_active_memberships_do_not_establish_context(): void
    {
        $user = User::factory()->create();
        $organization = $this->hierarchy->createOrganization('Example International', 'example');
        $this->actingAs($user);

        $this->assertFalse($this->context()->hasContext());

        $membership = $this->identity->invite($organization, $user);
        $this->context()->forgetResolved();
        $this->assertFalse($this->context()->hasContext());

        $membership->forceFill(['status' => OrganizationMembershipStatus::SUSPENDED, 'suspended_at' => now()])->save();
        $this->context()->forgetResolved();
        $this->assertFalse($this->context()->hasContext());

        $membership->forceFill(['status' => OrganizationMembershipStatus::REMOVED, 'removed_at' => now()])->save();
        $this->context()->forgetResolved();
        $this->assertFalse($this->context()->hasContext());
    }

    public function test_one_active_membership_resolves_and_organization_status_is_revalidated(): void
    {
        $user = User::factory()->create();
        $organization = $this->hierarchy->createOrganization('Example International', 'example');
        $membership = $this->identity->bootstrapAdministrator($organization, $user, 7001);
        $this->actingAs($user);

        $this->assertSame($membership->id, $this->context()->currentMembership()?->id);
        $this->assertSame($organization->id, $this->context()->currentOrganizationId());

        foreach ([OrganizationStatus::SUSPENDED, OrganizationStatus::ARCHIVED] as $status) {
            $organization->forceFill(['status' => $status])->save();
            $this->context()->forgetResolved();
            $this->assertFalse($this->context()->hasContext());
            $organization->forceFill(['status' => OrganizationStatus::ACTIVE])->save();
        }
    }

    public function test_multiple_memberships_fail_closed_until_valid_distinct_session_selection(): void
    {
        $user = User::factory()->create();
        $first = $this->hierarchy->createOrganization('First', 'first');
        $second = $this->hierarchy->createOrganization('Second', 'second');
        $this->identity->bootstrapAdministrator($first, $user);
        $secondMembership = OrganizationMembership::create([
            'organization_id' => $second->id,
            'user_id' => $user->id,
            'status' => OrganizationMembershipStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        $this->identity->assignRole($secondMembership, OrganizationRole::ORGANIZATION_VIEWER, $second->rootUnit);
        $this->actingAs($user);

        $this->assertFalse($this->context()->hasContext());

        session(['active_organization_id' => 999999]);
        $this->context()->forgetResolved();
        $this->assertFalse($this->context()->hasContext());

        session(['active_organization_id' => $second->id]);
        $this->context()->forgetResolved();
        $this->assertSame($second->id, $this->context()->currentOrganizationId());
    }

    public function test_organization_session_and_context_never_mutate_tenant_context(): void
    {
        $user = User::factory()->create();
        $organization = $this->hierarchy->createOrganization('Example', 'example');
        $this->identity->bootstrapAdministrator($organization, $user);
        $this->actingAs($user);
        session(['active_organization_id' => $organization->id]);

        $this->assertTrue($this->context()->hasContext());
        $this->assertFalse(app(TenantContext::class)->hasContext());
        $this->assertFalse(session()->has('active_church_id'));
        $this->assertSame(0, ChurchMembership::count());
    }

    public function test_middleware_fails_closed_and_revalidates_suspension(): void
    {
        Route::middleware(['web', 'organization.context', 'organization.access'])
            ->get('/_test/organization-context', fn () => response('allowed'));
        $user = User::factory()->create();

        $this->actingAs($user)->get('/_test/organization-context')->assertForbidden();

        $organization = $this->hierarchy->createOrganization('Example', 'example');
        $membership = $this->identity->bootstrapAdministrator($organization, $user);
        $this->context()->forgetResolved();
        $this->get('/_test/organization-context')->assertOk()->assertSee('allowed');

        $secondAdmin = User::factory()->create();
        $secondMembership = $this->identity->invite($organization, $secondAdmin);
        $this->identity->activate($secondMembership);
        $this->identity->assignRole($secondMembership, OrganizationRole::ORGANIZATION_ADMINISTRATOR, $organization->rootUnit);
        $this->identity->suspend($membership, $secondAdmin->id);
        $this->context()->forgetResolved();
        $this->get('/_test/organization-context')->assertForbidden();
    }

    public function test_membership_and_role_lifecycle_is_audited_and_does_not_create_church_membership(): void
    {
        $organization = $this->hierarchy->createOrganization('Example', 'example');
        $first = User::factory()->create();
        $second = User::factory()->create();
        $admin = $this->identity->bootstrapAdministrator($organization, $first, 44);
        $membership = $this->identity->invite($organization, $second, $first->id);
        $membership = $this->identity->activate($membership, $first->id);
        $role = $this->identity->assignRole($membership, OrganizationRole::ORGANIZATION_VIEWER, $organization->rootUnit, $first->id);

        $this->assertSame(0, ChurchMembership::count());
        $this->identity->removeRole($role, $first->id);
        $this->identity->remove($membership, $first->id);
        $this->assertSame(0, ChurchMembership::count());
        $this->assertSame(OrganizationMembershipStatus::ACTIVE, $admin->fresh()->status);
        $this->assertGreaterThanOrEqual(8, OrganizationAuditEvent::where('organization_id', $organization->id)->count());
    }

    public function test_last_active_root_administrator_cannot_be_removed_or_lose_role(): void
    {
        $organization = $this->hierarchy->createOrganization('Example', 'example');
        $user = User::factory()->create();
        $membership = $this->identity->bootstrapAdministrator($organization, $user);
        $role = $membership->roleAssignments->first();

        foreach ([
            fn () => $this->identity->suspend($membership),
            fn () => $this->identity->remove($membership),
            fn () => $this->identity->suspendRole($role),
            fn () => $this->identity->removeRole($role),
        ] as $operation) {
            try {
                $operation();
                $this->fail('The final Organization Administrator invariant was bypassed.');
            } catch (DomainException) {
                $this->assertSame(OrganizationMembershipStatus::ACTIVE, $membership->fresh()->status);
                $this->assertSame('active', $role->fresh()->status->value);
            }
        }
    }

    private function context(): OrganizationContext
    {
        return app(OrganizationContext::class);
    }
}
