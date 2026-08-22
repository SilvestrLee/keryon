<?php

namespace Tests\Feature\Identity;

use App\Enums\ChurchRole;
use App\Exceptions\CannotRemoveFinalPrimaryAdministratorException;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrimaryAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_primary_membership_assigns_exactly_one_primary(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->create();

        ChurchMembership::createPrimary($church, $user, [ChurchRole::ADMINISTRATOR]);

        $this->assertSame(1, $church->memberships()->primary()->active()->count());
    }

    public function test_primary_designation_does_not_imply_the_care_role(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->create();

        $membership = ChurchMembership::createPrimary($church, $user, [ChurchRole::ADMINISTRATOR]);

        $this->assertTrue($membership->is_primary);
        $this->assertFalse($membership->hasRole(ChurchRole::CARE));
    }

    public function test_cannot_remove_the_final_active_primary_administrator(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->create();
        $membership = ChurchMembership::createPrimary($church, $user, [ChurchRole::ADMINISTRATOR]);

        $this->expectException(CannotRemoveFinalPrimaryAdministratorException::class);

        $membership->remove();
    }

    public function test_cannot_suspend_the_final_active_primary_administrator(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->create();
        $membership = ChurchMembership::createPrimary($church, $user, [ChurchRole::ADMINISTRATOR]);

        $this->expectException(CannotRemoveFinalPrimaryAdministratorException::class);

        $membership->suspend();
    }

    public function test_can_remove_a_primary_membership_once_another_active_primary_exists(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $membershipA = ChurchMembership::createPrimary($church, $userA, [ChurchRole::ADMINISTRATOR]);
        $membershipB = ChurchMembership::factory()->for($userB)->for($church)->primary()->create();

        $membershipA->remove();

        $this->assertSame(1, $church->memberships()->primary()->active()->count());
        $this->assertTrue($membershipB->fresh()->is_primary);
    }

    public function test_transfer_primary_leaves_exactly_one_active_primary(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $membershipA = ChurchMembership::createPrimary($church, $userA, [ChurchRole::ADMINISTRATOR]);
        $membershipB = ChurchMembership::factory()->for($userB)->for($church)->create();

        ChurchMembership::transferPrimary($membershipA, $membershipB);

        $this->assertFalse($membershipA->fresh()->is_primary);
        $this->assertTrue($membershipB->fresh()->is_primary);
        $this->assertSame(1, $church->memberships()->primary()->active()->count());
    }

    public function test_transfer_primary_across_different_churches_is_rejected(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b']);
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $membershipA = ChurchMembership::createPrimary($churchA, $userA, [ChurchRole::ADMINISTRATOR]);
        $membershipB = ChurchMembership::createPrimary($churchB, $userB, [ChurchRole::ADMINISTRATOR]);

        $this->expectException(\InvalidArgumentException::class);

        ChurchMembership::transferPrimary($membershipA, $membershipB);
    }
}
