<?php

namespace Tests\Feature\Identity;

use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChurchMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_one_membership(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->forChurch($church)->create();

        $this->assertCount(1, $user->memberships);
    }

    public function test_user_can_have_multiple_memberships(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b']);
        $user = User::factory()->create();

        ChurchMembership::factory()->for($user)->for($churchA)->primary()->create();
        ChurchMembership::factory()->for($user)->for($churchB)->primary()->create();

        $this->assertCount(2, $user->fresh()->memberships);
    }

    public function test_church_can_have_multiple_memberships(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        ChurchMembership::factory()->for($userA)->for($church)->primary()->create();
        ChurchMembership::factory()->for($userB)->for($church)->create();

        $this->assertCount(2, $church->fresh()->memberships);
    }

    public function test_duplicate_membership_for_the_same_church_and_user_is_rejected(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->create();

        ChurchMembership::factory()->for($user)->for($church)->create();

        $this->expectException(QueryException::class);

        ChurchMembership::factory()->for($user)->for($church)->create();
    }

    public function test_inactive_membership_cannot_establish_tenant_context(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->create();
        ChurchMembership::factory()->for($user)->for($church)->suspended()->create();

        $this->actingAs($user);

        $this->assertFalse(app(TenantContext::class)->hasContext());
    }

    public function test_removed_membership_cannot_establish_tenant_context(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->create();
        ChurchMembership::factory()->for($user)->for($church)->removed()->create();

        $this->actingAs($user);

        $this->assertFalse(app(TenantContext::class)->hasContext());
    }

    public function test_composable_roles_can_be_assigned_to_one_membership(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()
            ->forChurch($church, [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS])
            ->create();

        $membership = $user->memberships()->first();

        $this->assertTrue($membership->hasRole(ChurchRole::ADMINISTRATOR));
        $this->assertTrue($membership->hasRole(ChurchRole::COMMUNICATIONS));
        $this->assertFalse($membership->hasRole(ChurchRole::CARE));
    }

    public function test_duplicate_role_on_the_same_membership_is_not_created_twice(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::CARE])->create();
        $membership = $user->memberships()->first();

        $membership->assignRoles([ChurchRole::CARE]);

        $this->assertCount(1, $membership->roles()->where('role', ChurchRole::CARE->value)->get());
    }

    /**
     * Locks in the exact contract K-AUTH-001 will depend on: persisted
     * roles must be retrievable through a freshly reloaded membership via
     * all three access paths, and roleValues() must return real strings,
     * not ChurchRole instances — found and fixed during
     * K-IDENTITY-001B-R4's ORM integrity check.
     */
    public function test_persisted_roles_are_retrievable_through_a_fresh_reload(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()
            ->forChurch($church, [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS, ChurchRole::CARE])
            ->create();
        $membershipId = $user->memberships()->first()->id;

        $fresh = ChurchMembership::query()->find($membershipId);

        $this->assertCount(3, $fresh->roles()->pluck('role'));
        $this->assertCount(3, $fresh->roles);
        $this->assertSame(
            ['administrator', 'care', 'communications'],
            collect($fresh->roleValues())->sort()->values()->all(),
        );
        foreach ($fresh->roleValues() as $value) {
            $this->assertIsString($value);
        }
        $this->assertTrue($fresh->hasRole(ChurchRole::ADMINISTRATOR));
        $this->assertTrue($fresh->hasRole(ChurchRole::COMMUNICATIONS));
        $this->assertTrue($fresh->hasRole(ChurchRole::CARE));
    }
}
