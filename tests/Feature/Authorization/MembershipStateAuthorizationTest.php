<?php

namespace Tests\Feature\Authorization;

use App\Enums\ChurchRole;
use App\Enums\MembershipStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ContentItem;
use App\Models\PrayerRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stored roles do not grant capability without an active membership and an
 * active Church — TenantContext remains authoritative. See K-AUTH-001B
 * §44-§45.
 */
class MembershipStateAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function membershipWithStatus(MembershipStatus $status, array $roles = [ChurchRole::CARE]): User
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'state-church-'.uniqid()]);
        $user = User::factory()->create();

        $membership = ChurchMembership::factory()->for($user)->for($church)->state(['status' => $status])->create();
        $membership->assignRoles($roles);

        return $user;
    }

    public function test_invited_membership_cannot_authorize(): void
    {
        $user = $this->membershipWithStatus(MembershipStatus::INVITED);
        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', PrayerRequest::class));
        $this->assertFalse($user->can('viewAny', ContentItem::class));
    }

    public function test_suspended_membership_cannot_authorize(): void
    {
        $user = $this->membershipWithStatus(MembershipStatus::SUSPENDED);
        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', PrayerRequest::class));
    }

    public function test_removed_membership_cannot_authorize(): void
    {
        $user = $this->membershipWithStatus(MembershipStatus::REMOVED);
        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', PrayerRequest::class));
    }

    public function test_stored_roles_persist_but_grant_nothing_once_suspended(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'state-church-persist']);
        $user = User::factory()->forChurch($church, [ChurchRole::CARE, ChurchRole::COMMUNICATIONS])->create();

        $membership = $user->memberships()->first();
        $membership->suspend();

        $this->actingAs($user);

        // Roles are still stored on the membership...
        $this->assertTrue($membership->fresh()->hasRole(ChurchRole::CARE));
        // ...but the membership itself no longer authorizes anything.
        $this->assertFalse($user->can('viewAny', PrayerRequest::class));
        $this->assertFalse($user->can('viewAny', ContentItem::class));
    }

    public function test_inactive_church_denies_authorization_despite_active_membership_and_role(): void
    {
        $church = Church::create(['name' => 'Inactive Church', 'slug' => 'inactive-church', 'is_active' => false]);
        $user = User::factory()->forChurch($church, [ChurchRole::CARE])->create();

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', PrayerRequest::class));
    }

    public function test_no_memberships_at_all_denies_authorization(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', PrayerRequest::class));
        $this->assertFalse($user->can('viewAny', ContentItem::class));
    }
}
