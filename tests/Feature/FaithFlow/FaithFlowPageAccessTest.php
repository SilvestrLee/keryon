<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowRunStatus;
use App\Filament\Pages\FaithFlow;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\FaithFlowRun;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-FAITHFLOW-001F §68 — page access matrix, including direct-URL denial
 * (§60/§61). Reuses the exact same authorization boundary as every other
 * FaithFlow milestone — no new capability, no broadened permission.
 */
class FaithFlowPageAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_communications_can_access(): void
    {
        $church = Church::create(['name' => 'Access Church', 'slug' => 'ff-access-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        Livewire::test(FaithFlow::class)->assertOk();
    }

    public function test_care_only_denied(): void
    {
        $church = Church::create(['name' => 'Access Church Care', 'slug' => 'ff-access-church-care']);
        $user = User::factory()->forChurch($church, [ChurchRole::CARE])->create();
        $this->actingAs($user);

        $this->assertFalse(FaithFlow::canAccess());
    }

    public function test_administrator_only_denied(): void
    {
        $church = Church::create(['name' => 'Access Church Admin', 'slug' => 'ff-access-church-admin']);
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();
        $this->actingAs($user);

        $this->assertFalse(FaithFlow::canAccess());
    }

    public function test_administrator_plus_communications_allowed(): void
    {
        $church = Church::create(['name' => 'Access Church Combo', 'slug' => 'ff-access-church-combo']);
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $this->assertTrue(FaithFlow::canAccess());
    }

    public function test_primary_only_denied(): void
    {
        $church = Church::create(['name' => 'Access Church Primary', 'slug' => 'ff-access-church-primary']);
        $user = User::factory()->forChurch($church, [], primary: true)->create();
        $this->actingAs($user);

        $this->assertFalse(FaithFlow::canAccess());
    }

    public function test_no_context_denied(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertFalse(FaithFlow::canAccess());
    }

    public function test_inactive_church_denied(): void
    {
        $church = Church::create(['name' => 'Access Church Inactive', 'slug' => 'ff-access-church-inactive', 'is_active' => false]);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $this->assertFalse(FaithFlow::canAccess());
    }

    public function test_suspended_membership_denied(): void
    {
        $church = Church::create(['name' => 'Access Church Suspended', 'slug' => 'ff-access-church-suspended']);
        $user = User::factory()->create();
        $membership = ChurchMembership::factory()->for($user)->for($church)->suspended()->create();
        $membership->assignRoles([ChurchRole::COMMUNICATIONS]);
        $this->actingAs($user);

        $this->assertFalse(FaithFlow::canAccess());
    }

    /**
     * Uses Livewire::test() directly rather than a real HTTP $this->get()
     * call — matches the established pattern in
     * ContentItemResourceAccessTest (see its own cross-Church direct-URL
     * tests). A real HTTP request to any authenticated Filament route in
     * this test environment always 403s at Filament's own Authenticate
     * middleware first (User does not implement FilamentUser, and
     * APP_ENV is "testing" not "local" — see that middleware's own
     * app.env-gated fallback), which would mask the actual
     * tenancy/Policy-level assertion this test exists to prove.
     *
     * Expects ModelNotFoundException, not AuthorizationException: Church
     * B's run is invisible to Church A's tenant-scoped query before
     * Gate::authorize('view', ...) ever runs — the same "invisible, not
     * merely unauthorized" invariant CrossChurchFaithFlowAccessTest
     * already established for this exact model.
     */
    public function test_direct_url_denies_viewing_another_churchs_run(): void
    {
        $churchA = Church::create(['name' => 'Access Church A', 'slug' => 'ff-access-church-a']);
        $churchB = Church::create(['name' => 'Access Church B', 'slug' => 'ff-access-church-b']);
        $runB = FaithFlowRun::factory()->forChurch($churchB)->create(['status' => FaithFlowRunStatus::DRAFT]);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($userA);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(FaithFlow::class, ['run' => $runB->id]);
    }

    public function test_direct_url_returns_not_found_for_a_nonexistent_run(): void
    {
        $church = Church::create(['name' => 'Access Church Missing', 'slug' => 'ff-access-church-missing']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(FaithFlow::class, ['run' => 999999]);
    }
}
