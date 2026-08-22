<?php

namespace Tests\Feature\Identity;

use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillChurchMembershipsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_church_with_exactly_one_legacy_user_is_backfilled_as_primary_automatically(): void
    {
        $church = Church::create(['name' => 'Solo Church', 'slug' => 'solo-church']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->artisan('identity:backfill-memberships')->assertSuccessful();

        $membership = ChurchMembership::where('church_id', $church->id)->first();

        $this->assertNotNull($membership);
        $this->assertSame($user->id, $membership->user_id);
        $this->assertTrue($membership->is_primary);
        $this->assertTrue($membership->hasRole(ChurchRole::ADMINISTRATOR));
        $this->assertTrue($membership->hasRole(ChurchRole::COMMUNICATIONS));
        $this->assertTrue($membership->hasRole(ChurchRole::CARE));
    }

    public function test_a_church_with_multiple_legacy_users_and_no_mapping_is_refused(): void
    {
        $church = Church::create(['name' => 'Ambiguous Church', 'slug' => 'ambiguous-church']);
        User::factory()->create(['church_id' => $church->id]);
        User::factory()->create(['church_id' => $church->id]);

        $this->artisan('identity:backfill-memberships')->assertFailed();

        $this->assertSame(0, ChurchMembership::where('church_id', $church->id)->count());
    }

    public function test_a_church_with_multiple_legacy_users_is_backfilled_with_an_explicit_primary_mapping(): void
    {
        $church = Church::create(['name' => 'Ambiguous Church', 'slug' => 'ambiguous-church']);
        $userA = User::factory()->create(['church_id' => $church->id]);
        $userB = User::factory()->create(['church_id' => $church->id]);

        $this->artisan('identity:backfill-memberships', ["--primary" => ["{$church->id}:{$userB->id}"]])
            ->assertSuccessful();

        $this->assertSame(2, ChurchMembership::where('church_id', $church->id)->count());
        $this->assertTrue(ChurchMembership::where('user_id', $userB->id)->first()->is_primary);
        $this->assertFalse(ChurchMembership::where('user_id', $userA->id)->first()->is_primary);
    }

    public function test_a_church_with_zero_legacy_users_is_left_alone(): void
    {
        $church = Church::create(['name' => 'Empty Church', 'slug' => 'empty-church']);

        $this->artisan('identity:backfill-memberships')->assertSuccessful();

        $this->assertSame(0, ChurchMembership::where('church_id', $church->id)->count());
    }

    public function test_dry_run_makes_no_database_changes(): void
    {
        $church = Church::create(['name' => 'Solo Church', 'slug' => 'solo-church']);
        User::factory()->create(['church_id' => $church->id]);

        $this->artisan('identity:backfill-memberships', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, ChurchMembership::count());
    }

    public function test_running_twice_is_idempotent(): void
    {
        $church = Church::create(['name' => 'Solo Church', 'slug' => 'solo-church']);
        User::factory()->create(['church_id' => $church->id]);

        $this->artisan('identity:backfill-memberships')->assertSuccessful();
        $this->artisan('identity:backfill-memberships')->assertSuccessful();

        $this->assertSame(1, ChurchMembership::where('church_id', $church->id)->count());
    }
}
