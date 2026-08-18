<?php

namespace Tests\Feature\Authorization;

use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ContentItem;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves capabilities belong to ChurchMembership, not User — a Care role
 * in one church confers nothing in another church the same person also
 * belongs to. See K-AUTH-001B §41/§100.
 */
class MultiChurchAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_capabilities_follow_the_active_church_not_the_user(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'multi-church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'multi-church-b']);

        $user = User::factory()->create();

        ChurchMembership::factory()->for($user)->for($churchA)->create()->assignRoles([ChurchRole::CARE]);
        ChurchMembership::factory()->for($user)->for($churchB)->create()->assignRoles([ChurchRole::COMMUNICATIONS]);

        $this->actingAs($user);

        // Active context: Church A (single active membership resolves
        // without a session selection only when there's exactly one — here
        // there are two, so the session selection is mandatory).
        session(['active_church_id' => $churchA->id]);
        app(TenantContext::class)->forgetResolved();

        $this->assertSame($churchA->id, app(TenantContext::class)->currentChurchId());
        $this->assertTrue($user->can('viewAny', PrayerRequest::class));
        $this->assertFalse($user->can('viewAny', ContentItem::class));

        // Switch active context to Church B, same authenticated User.
        session(['active_church_id' => $churchB->id]);
        app(TenantContext::class)->forgetResolved();

        $this->assertSame($churchB->id, app(TenantContext::class)->currentChurchId());
        $this->assertFalse($user->can('viewAny', PrayerRequest::class));
        $this->assertTrue($user->can('viewAny', ContentItem::class));
    }

    public function test_records_do_not_leak_across_the_same_users_two_church_memberships(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'multi-record-church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'multi-record-church-b']);

        $user = User::factory()->create();
        ChurchMembership::factory()->for($user)->for($churchA)->create()->assignRoles([ChurchRole::CARE]);
        ChurchMembership::factory()->for($user)->for($churchB)->create()->assignRoles([ChurchRole::CARE]);

        $this->actingAs($user);

        session(['active_church_id' => $churchA->id]);
        app(TenantContext::class)->forgetResolved();
        $requestA = PrayerRequest::create(['request' => 'Church A request.']);

        session(['active_church_id' => $churchB->id]);
        app(TenantContext::class)->forgetResolved();
        $requestB = PrayerRequest::create(['request' => 'Church B request.']);

        // Still acting as Church B — Church A's record must not resolve,
        // even though it belongs to the same authenticated User via
        // another of their own memberships, and even though both
        // memberships hold Care.
        $this->assertFalse($user->can('view', $requestA));
        $this->assertTrue($user->can('view', $requestB));

        $ids = PrayerRequest::query()->pluck('id')->all();
        $this->assertContains($requestB->id, $ids);
        $this->assertNotContains($requestA->id, $ids);
    }

    public function test_no_valid_session_selection_among_multiple_active_memberships_fails_closed(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'multi-noselect-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'multi-noselect-b']);

        $user = User::factory()->create();
        ChurchMembership::factory()->for($user)->for($churchA)->create()->assignRoles([ChurchRole::CARE]);
        ChurchMembership::factory()->for($user)->for($churchB)->create()->assignRoles([ChurchRole::COMMUNICATIONS]);

        $this->actingAs($user);

        $this->assertNull(app(TenantContext::class)->currentChurchId());
        $this->assertFalse($user->can('viewAny', PrayerRequest::class));
        $this->assertFalse($user->can('viewAny', ContentItem::class));
    }
}
