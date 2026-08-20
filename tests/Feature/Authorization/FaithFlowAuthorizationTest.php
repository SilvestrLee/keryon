<?php

namespace Tests\Feature\Authorization;

use App\Enums\ChurchRole;
use App\Enums\MembershipStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\FaithFlowRun;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the FaithFlow responsibility boundary — see K-FAITHFLOW-001B
 * §3.2/§3.3/§30-§34. FaithFlow requires Communications (faithflow.use, which
 * already existed and was already granted before this milestone —
 * K-FAITHFLOW-001A §6); Administrator and Care do not inherit it.
 */
class FaithFlowAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function memberOf(Church $church, array $roles = [], bool $primary = false): User
    {
        return User::factory()->forChurch($church, $roles, $primary)->create();
    }

    protected function seedRun(User $author): FaithFlowRun
    {
        $this->actingAs($author);

        return FaithFlowRun::factory()->forChurch($author->memberships()->first()->church)->create();
    }

    public function test_communications_can_use_faithflow(): void
    {
        $church = Church::create(['name' => 'Comms Church', 'slug' => 'faithflow-comms-church']);
        $author = $this->memberOf($church, [ChurchRole::COMMUNICATIONS]);
        $run = $this->seedRun($author);

        $this->assertTrue($author->can('viewAny', FaithFlowRun::class));
        $this->assertTrue($author->can('create', FaithFlowRun::class));
        $this->assertTrue($author->can('view', $run));
        $this->assertTrue($author->can('update', $run));
    }

    /**
     * K-FAITHFLOW-001C §26: analyze() is authorized identically to update()
     * (no separate faithflow.analyze capability) — a small, representative
     * check, not a full re-run of the matrix above.
     */
    public function test_analyze_ability_mirrors_update_across_the_role_matrix(): void
    {
        $church = Church::create(['name' => 'Analyze Ability Church', 'slug' => 'faithflow-analyze-ability-church']);
        $comms = $this->memberOf($church, [ChurchRole::COMMUNICATIONS]);
        $run = $this->seedRun($comms);

        $this->assertTrue($comms->can('analyze', $run));

        $care = $this->memberOf($church, [ChurchRole::CARE]);
        $this->actingAs($care);
        $this->assertFalse($care->can('analyze', $run));
    }

    public function test_administrator_only_cannot_use_faithflow(): void
    {
        $church = Church::create(['name' => 'Admin Church', 'slug' => 'faithflow-admin-church']);
        $comms = $this->memberOf($church, [ChurchRole::COMMUNICATIONS]);
        $run = $this->seedRun($comms);

        $admin = $this->memberOf($church, [ChurchRole::ADMINISTRATOR]);
        $this->actingAs($admin);

        $this->assertFalse($admin->can('viewAny', FaithFlowRun::class));
        $this->assertFalse($admin->can('create', FaithFlowRun::class));
        $this->assertFalse($admin->can('view', $run));
    }

    public function test_care_only_cannot_use_faithflow(): void
    {
        $church = Church::create(['name' => 'Care Church', 'slug' => 'faithflow-care-church']);
        $comms = $this->memberOf($church, [ChurchRole::COMMUNICATIONS]);
        $run = $this->seedRun($comms);

        $care = $this->memberOf($church, [ChurchRole::CARE]);
        $this->actingAs($care);

        $this->assertFalse($care->can('viewAny', FaithFlowRun::class));
        $this->assertFalse($care->can('create', FaithFlowRun::class));
        $this->assertFalse($care->can('view', $run));
    }

    public function test_primary_only_cannot_use_faithflow(): void
    {
        $church = Church::create(['name' => 'Primary Church', 'slug' => 'faithflow-primary-church']);
        $user = $this->memberOf($church, [], primary: true);

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', FaithFlowRun::class));
        $this->assertFalse($user->can('create', FaithFlowRun::class));
    }

    public function test_administrator_plus_communications_can_use_faithflow(): void
    {
        $church = Church::create(['name' => 'Combo Church', 'slug' => 'faithflow-combo-church']);
        $user = $this->memberOf($church, [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS]);

        $this->actingAs($user);

        $this->assertTrue($user->can('viewAny', FaithFlowRun::class));
        $this->assertTrue($user->can('create', FaithFlowRun::class));
    }

    public function test_care_plus_communications_can_use_faithflow(): void
    {
        $church = Church::create(['name' => 'Combo Care Church', 'slug' => 'faithflow-combo-care-church']);
        $user = $this->memberOf($church, [ChurchRole::COMMUNICATIONS, ChurchRole::CARE]);

        $this->actingAs($user);

        $this->assertTrue($user->can('viewAny', FaithFlowRun::class));
        $this->assertTrue($user->can('create', FaithFlowRun::class));
    }

    public function test_administrator_plus_care_still_denied_faithflow(): void
    {
        $church = Church::create(['name' => 'Admin Care Church', 'slug' => 'faithflow-admin-care-church']);
        $user = $this->memberOf($church, [ChurchRole::ADMINISTRATOR, ChurchRole::CARE]);

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', FaithFlowRun::class));
        $this->assertFalse($user->can('create', FaithFlowRun::class));
    }

    public function test_cross_church_run_access_is_denied_even_with_communications(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'faithflow-church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'faithflow-church-b']);

        $commsB = $this->memberOf($churchB, [ChurchRole::COMMUNICATIONS]);
        $runB = $this->seedRun($commsB);

        $commsA = $this->memberOf($churchA, [ChurchRole::COMMUNICATIONS]);
        $this->actingAs($commsA);

        $this->assertFalse($commsA->can('view', $runB));
        $this->assertFalse($commsA->can('update', $runB));
    }

    public function test_no_tenant_context_denies_faithflow_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', FaithFlowRun::class));
    }

    public function test_suspended_membership_cannot_use_faithflow(): void
    {
        $church = Church::create(['name' => 'Suspended Church', 'slug' => 'faithflow-suspended-church']);
        $user = User::factory()->create();

        $membership = ChurchMembership::factory()->for($user)->for($church)
            ->state(['status' => MembershipStatus::SUSPENDED])
            ->create();
        $membership->assignRoles([ChurchRole::COMMUNICATIONS]);

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', FaithFlowRun::class));
    }

    public function test_inactive_church_denies_faithflow_despite_active_communications_membership(): void
    {
        $church = Church::create(['name' => 'Inactive Church', 'slug' => 'faithflow-inactive-church', 'is_active' => false]);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', FaithFlowRun::class));
    }

    /**
     * The mandatory multi-Church regression — see K-FAITHFLOW-001B §32.
     * Capabilities follow the active membership (selected via
     * session('active_church_id')), not the authenticated User globally.
     */
    public function test_capabilities_and_records_follow_the_active_church_not_the_user(): void
    {
        $churchA = Church::create(['name' => 'Multi Church A', 'slug' => 'faithflow-multi-church-a']);
        $churchB = Church::create(['name' => 'Multi Church B', 'slug' => 'faithflow-multi-church-b']);

        $user = User::factory()->create();

        ChurchMembership::factory()->for($user)->for($churchA)->create()->assignRoles([ChurchRole::COMMUNICATIONS]);
        ChurchMembership::factory()->for($user)->for($churchB)->create()->assignRoles([ChurchRole::CARE]);

        $runA = FaithFlowRun::factory()->forChurch($churchA)->create();

        $this->actingAs($user);

        session(['active_church_id' => $churchA->id]);
        app(TenantContext::class)->forgetResolved();

        $this->assertSame($churchA->id, app(TenantContext::class)->currentChurchId());
        $this->assertTrue($user->can('viewAny', FaithFlowRun::class));
        $this->assertTrue($user->can('view', $runA));

        session(['active_church_id' => $churchB->id]);
        app(TenantContext::class)->forgetResolved();

        $this->assertSame($churchB->id, app(TenantContext::class)->currentChurchId());
        $this->assertFalse($user->can('viewAny', FaithFlowRun::class));
        $this->assertFalse($user->can('view', $runA));
    }
}
