<?php

namespace Tests\Feature\Authorization;

use App\Enums\ChurchRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use App\Enums\PlatformMembershipStatus;
use App\Enums\PlatformRole;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilamentPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_admin_request_uses_normal_filament_login_flow(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_user_without_church_membership_is_denied_by_the_admin_panel_gate(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel($this->panel('admin')));
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    #[DataProvider('nonAccessGrantingChurchStatuses')]
    public function test_non_access_granting_church_membership_is_denied(MembershipStatus $status): void
    {
        $user = User::factory()->create();
        $church = Church::factory()->create();
        ChurchMembership::factory()
            ->for($church)
            ->for($user)
            ->state(['status' => $status])
            ->create();

        $this->assertFalse($user->canAccessPanel($this->panel('admin')));
    }

    public function test_active_church_membership_at_an_inactive_church_is_denied(): void
    {
        $church = Church::factory()->inactive()->create();
        $user = User::factory()->forChurch($church)->create();

        $this->assertFalse($user->canAccessPanel($this->panel('admin')));
    }

    #[DataProvider('churchRoles')]
    public function test_each_church_role_may_enter_the_admin_panel_gate(ChurchRole $role): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [$role])->create();

        $this->assertTrue($user->canAccessPanel($this->panel('admin')));
    }

    public function test_active_church_membership_without_a_role_may_enter_the_coarse_panel_gate(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church)->create();

        $this->assertTrue($user->canAccessPanel($this->panel('admin')));
    }

    public function test_primary_flag_alone_does_not_override_membership_state(): void
    {
        $user = User::factory()->create();
        $church = Church::factory()->create();
        ChurchMembership::factory()
            ->for($church)
            ->for($user)
            ->state([
                'status' => MembershipStatus::INVITED,
                'is_primary' => true,
            ])
            ->create();

        $this->assertFalse($user->canAccessPanel($this->panel('admin')));
    }

    public function test_multiple_active_church_memberships_allow_panel_entry_but_tenant_selection_remains_downstream(): void
    {
        $user = User::factory()->create();
        foreach (Church::factory()->count(2)->create() as $church) {
            ChurchMembership::factory()->for($church)->for($user)->create();
        }

        $this->assertTrue($user->canAccessPanel($this->panel('admin')));

        $this->actingAs($user);
        $this->assertNull(app(TenantContext::class)->currentMembership());
    }

    public function test_legacy_users_church_id_without_membership_does_not_grant_admin_access(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->assertFalse($user->canAccessPanel($this->panel('admin')));
    }

    public function test_user_without_organization_membership_is_denied(): void
    {
        $this->assertFalse(User::factory()->create()->canAccessPanel($this->panel('organization')));
    }

    #[DataProvider('nonAccessGrantingOrganizationStatuses')]
    public function test_non_access_granting_organization_membership_is_denied(
        OrganizationMembershipStatus $status,
    ): void {
        $user = User::factory()->create();
        $organization = $this->organization();
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => $status,
        ]);

        $this->assertFalse($user->canAccessPanel($this->panel('organization')));
    }

    public function test_active_organization_membership_at_an_inactive_organization_is_denied(): void
    {
        $user = User::factory()->create();
        $organization = $this->organization(OrganizationStatus::SUSPENDED);
        $this->organizationMembership($user, $organization);

        $this->assertFalse($user->canAccessPanel($this->panel('organization')));
    }

    public function test_active_organization_membership_at_an_active_organization_is_allowed(): void
    {
        $user = User::factory()->create();
        $this->organizationMembership($user, $this->organization());

        $this->assertTrue($user->canAccessPanel($this->panel('organization')));
    }

    public function test_church_membership_alone_does_not_grant_organization_access(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();

        $this->assertFalse($user->canAccessPanel($this->panel('organization')));
    }

    public function test_cross_organization_state_allows_the_panel_gate_but_requires_context_selection(): void
    {
        $user = User::factory()->create();
        $first = $this->organization();
        $second = $this->organization();
        $this->organizationMembership($user, $first);
        $this->organizationMembership($user, $second);

        $this->assertTrue($user->canAccessPanel($this->panel('organization')));

        $this->actingAs($user);
        $this->assertNull(app(OrganizationContext::class)->currentMembership());

        session(['active_organization_id' => $second->id]);
        app(OrganizationContext::class)->forgetResolved();
        $this->assertSame($second->id, app(OrganizationContext::class)->currentOrganizationId());
    }

    public function test_user_without_platform_membership_is_denied(): void
    {
        $this->assertFalse(User::factory()->create()->canAccessPanel($this->panel('central')));
    }

    #[DataProvider('nonAccessGrantingPlatformStatuses')]
    public function test_non_access_granting_platform_membership_is_denied(
        PlatformMembershipStatus $status,
    ): void {
        $user = User::factory()->create();
        $this->platformMembership($user, $status);

        $this->assertFalse($user->canAccessPanel($this->panel('central')));
    }

    public function test_active_platform_membership_is_allowed(): void
    {
        $user = User::factory()->create();
        $this->platformMembership($user);

        $this->assertTrue($user->canAccessPanel($this->panel('central')));
    }

    public function test_customer_memberships_do_not_grant_central_access(): void
    {
        $church = Church::factory()->create();
        $churchUser = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();

        $organizationUser = User::factory()->create();
        $this->organizationMembership($organizationUser, $this->organization());

        $this->assertFalse($churchUser->canAccessPanel($this->panel('central')));
        $this->assertFalse($organizationUser->canAccessPanel($this->panel('central')));
    }

    public function test_unknown_panel_id_fails_closed(): void
    {
        $user = User::factory()->create();
        $church = Church::factory()->create();
        ChurchMembership::factory()->for($church)->for($user)->create();
        $this->organizationMembership($user, $this->organization());
        $this->platformMembership($user);

        $this->assertFalse($user->canAccessPanel((new Panel)->id('unknown')));
    }

    public function test_panel_checks_use_one_bounded_exists_query_without_hydrating_tenants(): void
    {
        $user = User::factory()->create();
        $church = Church::factory()->create();
        ChurchMembership::factory()->for($church)->for($user)->create();
        $this->organizationMembership($user, $this->organization());
        $this->platformMembership($user);
        $panels = [
            $this->panel('admin'),
            $this->panel('organization'),
            $this->panel('central'),
        ];

        DB::enableQueryLog();
        DB::flushQueryLog();

        foreach ($panels as $panel) {
            $this->assertTrue($user->canAccessPanel($panel));
        }

        $this->assertCount(3, DB::getQueryLog());
        $this->assertFalse($user->relationLoaded('memberships'));
        $this->assertFalse($user->relationLoaded('organizationMemberships'));
        $this->assertFalse($user->relationLoaded('platformMembership'));

        DB::disableQueryLog();
    }

    /** @return array<string, array{MembershipStatus}> */
    public static function nonAccessGrantingChurchStatuses(): array
    {
        return [
            'invited' => [MembershipStatus::INVITED],
            'suspended' => [MembershipStatus::SUSPENDED],
            'removed' => [MembershipStatus::REMOVED],
        ];
    }

    /** @return array<string, array{ChurchRole}> */
    public static function churchRoles(): array
    {
        return [
            'communications' => [ChurchRole::COMMUNICATIONS],
            'care' => [ChurchRole::CARE],
            'administrator' => [ChurchRole::ADMINISTRATOR],
        ];
    }

    /** @return array<string, array{OrganizationMembershipStatus}> */
    public static function nonAccessGrantingOrganizationStatuses(): array
    {
        return [
            'invited' => [OrganizationMembershipStatus::INVITED],
            'suspended' => [OrganizationMembershipStatus::SUSPENDED],
            'removed' => [OrganizationMembershipStatus::REMOVED],
        ];
    }

    /** @return array<string, array{PlatformMembershipStatus}> */
    public static function nonAccessGrantingPlatformStatuses(): array
    {
        return [
            'suspended' => [PlatformMembershipStatus::SUSPENDED],
            'removed' => [PlatformMembershipStatus::REMOVED],
        ];
    }

    private function panel(string $id): Panel
    {
        return Filament::getPanel($id);
    }

    private function organization(OrganizationStatus $status = OrganizationStatus::ACTIVE): Organization
    {
        return Organization::create([
            'name' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(),
            'status' => $status,
        ]);
    }

    private function organizationMembership(User $user, Organization $organization): OrganizationMembership
    {
        return OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => OrganizationMembershipStatus::ACTIVE,
            'joined_at' => now(),
        ]);
    }

    private function platformMembership(
        User $user,
        PlatformMembershipStatus $status = PlatformMembershipStatus::ACTIVE,
    ): PlatformMembership {
        return PlatformMembership::create([
            'user_id' => $user->id,
            'role' => PlatformRole::SUPPORT,
            'status' => $status,
            'activated_at' => now(),
            'suspended_at' => $status === PlatformMembershipStatus::SUSPENDED ? now() : null,
            'removed_at' => $status === PlatformMembershipStatus::REMOVED ? now() : null,
        ]);
    }
}
