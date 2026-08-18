<?php

namespace Tests\Feature\Identity;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_active_membership_resolves_automatically(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->forChurch($church)->create();

        $this->actingAs($user);

        $this->assertSame($church->id, app(TenantContext::class)->currentChurchId());
        $this->assertTrue(app(TenantContext::class)->hasContext());
    }

    public function test_no_membership_fails_closed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertFalse(app(TenantContext::class)->hasContext());
        $this->assertNull(app(TenantContext::class)->currentChurchId());
        $this->assertSame(0, ContentItem::count());
    }

    public function test_suspended_membership_fails_closed(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->create();
        ChurchMembership::factory()->for($user)->for($church)->suspended()->create();

        $this->actingAs($user);

        $this->assertFalse(app(TenantContext::class)->hasContext());
    }

    public function test_removed_membership_fails_closed(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->create();
        ChurchMembership::factory()->for($user)->for($church)->removed()->create();

        $this->actingAs($user);

        $this->assertFalse(app(TenantContext::class)->hasContext());
    }

    public function test_inactive_church_fails_closed_even_with_an_active_membership(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church', 'is_active' => false]);
        $user = User::factory()->create();
        ChurchMembership::factory()->for($user)->for($church)->create();

        $this->actingAs($user);

        $this->assertFalse(app(TenantContext::class)->hasContext());
    }

    public function test_multiple_active_memberships_with_a_valid_session_selection_resolves_the_selected_church(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b']);
        $user = User::factory()->create();
        ChurchMembership::factory()->for($user)->for($churchA)->primary()->create();
        ChurchMembership::factory()->for($user)->for($churchB)->create();

        session(['active_church_id' => $churchB->id]);
        $this->actingAs($user);

        $this->assertSame($churchB->id, app(TenantContext::class)->currentChurchId());
    }

    public function test_multiple_active_memberships_without_a_valid_selection_requires_explicit_selection(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b']);
        $user = User::factory()->create();
        ChurchMembership::factory()->for($user)->for($churchA)->primary()->create();
        ChurchMembership::factory()->for($user)->for($churchB)->create();

        $this->actingAs($user);

        $this->assertFalse(app(TenantContext::class)->hasContext());
    }

    public function test_selected_church_without_a_matching_membership_fails_closed(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b']);
        $user = User::factory()->forChurch($churchA)->create();

        session(['active_church_id' => $churchB->id]);
        $this->actingAs($user);

        $this->assertSame($churchA->id, app(TenantContext::class)->currentChurchId());
    }

    public function test_switching_between_users_within_one_request_does_not_leak_the_prior_users_context(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b']);
        $userA = User::factory()->forChurch($churchA)->create();
        $userB = User::factory()->forChurch($churchB)->create();

        $this->actingAs($userA);
        $this->assertSame($churchA->id, app(TenantContext::class)->currentChurchId());

        $this->actingAs($userB);
        $this->assertSame($churchB->id, app(TenantContext::class)->currentChurchId());
    }
}
