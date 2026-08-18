<?php

namespace Tests\Feature\Identity;

use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves membership revocation takes effect on the very next resolution,
 * without requiring logout — Blueprint v1.4.1 §6, engineering doc §6.
 * A fresh TenantContext instance simulates a new request re-resolving
 * from scratch, exactly as the real per-request container binding does.
 */
class MembershipRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspending_a_membership_blocks_access_on_the_next_resolution_without_logout(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->forChurch($church)->create();
        $this->actingAs($user);

        $this->assertTrue(app(TenantContext::class)->hasContext());

        $user->memberships()->first()->suspend();

        // Simulate the next request: no logout occurred, but a fresh
        // TenantContext must re-validate from the database.
        $freshContext = new TenantContext();

        $this->assertFalse($freshContext->hasContext());
    }

    public function test_removing_a_membership_blocks_tenant_scoped_queries_on_the_next_resolution(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->forChurch($church)->create();
        $this->actingAs($user);

        ContentItem::create([
            'title' => 'Before revocation',
            'content_type' => 'general',
            'body' => 'Body text.',
        ]);

        $user->memberships()->first()->remove();

        $freshContext = new TenantContext();
        app()->instance(TenantContext::class, $freshContext);

        $this->assertSame(0, ContentItem::count());
    }

    public function test_deactivating_the_church_blocks_access_on_the_next_resolution(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->forChurch($church)->create();
        $this->actingAs($user);

        $this->assertTrue(app(TenantContext::class)->hasContext());

        $church->update(['is_active' => false]);

        $freshContext = new TenantContext();

        $this->assertFalse($freshContext->hasContext());
    }
}
