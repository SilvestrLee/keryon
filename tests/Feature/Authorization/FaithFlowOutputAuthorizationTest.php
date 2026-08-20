<?php

namespace Tests\Feature\Authorization;

use App\Enums\ChurchRole;
use App\Enums\MembershipStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FaithFlowOutputPolicy was built in K-FAITHFLOW-001B (generate/regenerate/
 * approve abilities, all delegating to update()) but never actually tested
 * — 001B's own authorization test file only covered FaithFlowRunPolicy. This
 * closes that gap, per K-FAITHFLOW-001D §43/§64. Same capability
 * (faithflow.use) and tenancy rules as FaithFlowRunPolicy — no new
 * capability was introduced.
 */
class FaithFlowOutputAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function memberOf(Church $church, array $roles = [], bool $primary = false): User
    {
        return User::factory()->forChurch($church, $roles, $primary)->create();
    }

    protected function outputFor(Church $church): FaithFlowOutput
    {
        $run = FaithFlowRun::factory()->forChurch($church)->create();

        return FaithFlowOutput::factory()->forRun($run)->create();
    }

    public function test_communications_can_act_on_outputs(): void
    {
        $church = Church::create(['name' => 'Comms Church', 'slug' => 'output-comms-church']);
        $output = $this->outputFor($church);
        $comms = $this->memberOf($church, [ChurchRole::COMMUNICATIONS]);

        $this->actingAs($comms);

        $this->assertTrue($comms->can('viewAny', FaithFlowOutput::class));
        $this->assertTrue($comms->can('view', $output));
        $this->assertTrue($comms->can('update', $output));
        $this->assertTrue($comms->can('generate', $output));
        $this->assertTrue($comms->can('regenerate', $output));
        $this->assertTrue($comms->can('approve', $output));
    }

    public function test_care_only_cannot_act_on_outputs(): void
    {
        $church = Church::create(['name' => 'Care Church', 'slug' => 'output-care-church']);
        $output = $this->outputFor($church);
        $care = $this->memberOf($church, [ChurchRole::CARE]);

        $this->actingAs($care);

        $this->assertFalse($care->can('viewAny', FaithFlowOutput::class));
        $this->assertFalse($care->can('view', $output));
        $this->assertFalse($care->can('generate', $output));
        $this->assertFalse($care->can('regenerate', $output));
    }

    public function test_administrator_only_cannot_act_on_outputs(): void
    {
        $church = Church::create(['name' => 'Admin Church', 'slug' => 'output-admin-church']);
        $output = $this->outputFor($church);
        $admin = $this->memberOf($church, [ChurchRole::ADMINISTRATOR]);

        $this->actingAs($admin);

        $this->assertFalse($admin->can('viewAny', FaithFlowOutput::class));
        $this->assertFalse($admin->can('generate', $output));
    }

    public function test_primary_only_cannot_act_on_outputs(): void
    {
        $church = Church::create(['name' => 'Primary Church', 'slug' => 'output-primary-church']);
        $output = $this->outputFor($church);
        $primary = $this->memberOf($church, [], primary: true);

        $this->actingAs($primary);

        $this->assertFalse($primary->can('viewAny', FaithFlowOutput::class));
        $this->assertFalse($primary->can('generate', $output));
    }

    public function test_administrator_plus_communications_can_act_on_outputs(): void
    {
        $church = Church::create(['name' => 'Combo Church', 'slug' => 'output-combo-church']);
        $output = $this->outputFor($church);
        $user = $this->memberOf($church, [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS]);

        $this->actingAs($user);

        $this->assertTrue($user->can('generate', $output));
        $this->assertTrue($user->can('regenerate', $output));
    }

    public function test_cross_church_output_access_is_denied_even_with_communications(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'output-church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'output-church-b']);

        $outputB = $this->outputFor($churchB);
        $commsA = $this->memberOf($churchA, [ChurchRole::COMMUNICATIONS]);

        $this->actingAs($commsA);

        $this->assertFalse($commsA->can('view', $outputB));
        $this->assertFalse($commsA->can('generate', $outputB));
        $this->assertFalse($commsA->can('regenerate', $outputB));
    }

    public function test_suspended_membership_cannot_act_on_outputs(): void
    {
        $church = Church::create(['name' => 'Suspended Church', 'slug' => 'output-suspended-church']);
        $output = $this->outputFor($church);
        $user = User::factory()->create();

        $membership = ChurchMembership::factory()->for($user)->for($church)
            ->state(['status' => MembershipStatus::SUSPENDED])
            ->create();
        $membership->assignRoles([ChurchRole::COMMUNICATIONS]);

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', FaithFlowOutput::class));
        $this->assertFalse($user->can('generate', $output));
    }

    public function test_inactive_church_denies_output_access_despite_active_communications_membership(): void
    {
        $church = Church::create(['name' => 'Inactive Church', 'slug' => 'output-inactive-church', 'is_active' => false]);
        $output = $this->outputFor($church);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', FaithFlowOutput::class));
        $this->assertFalse($user->can('generate', $output));
    }

    public function test_no_tenant_context_denies_output_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', FaithFlowOutput::class));
    }
}
