<?php

namespace Tests\Feature\ContentStudio;

use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

// No explicit user-deletion governance exists elsewhere in the
// repository (no soft-deletes on User, no documented policy against
// deleting a staff account). This test exists to lock in the defensive
// design decision from K-CONTENT-002 §2 regardless: content is
// church-owned, attribution is not ownership.
class ContentItemAttributionSurvivalTest extends TestCase
{
    use RefreshDatabase;

    public function test_removing_an_attribution_user_does_not_delete_church_content(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->forChurch($church)->create();
        $this->actingAs($user);

        $contentItem = ContentItem::create([
            'title' => 'Content Surviving User Removal',
            'content_type' => 'general',
            'body' => 'Body text.',
        ]);

        // church_memberships.user_id is restrictOnDelete (Blueprint v1.4.1
        // preflight decision #7) — a membership is the access grant
        // itself, not attribution metadata. ChurchMembership::remove()
        // only changes status (kept for audit history), which does not
        // clear the FK; the membership row itself must be gone before the
        // account can be deleted. This mirrors the real product ordering
        // the same decision specifies ("remove membership... before any
        // account deletion").
        $user->memberships()->first()->delete();
        $user->delete();

        $this->assertDatabaseHas('content_items', ['id' => $contentItem->id]);
    }

    public function test_attribution_fk_becomes_null_when_user_is_removed(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->forChurch($church)->create();
        $this->actingAs($user);

        $contentItem = ContentItem::create([
            'title' => 'Content Surviving User Removal',
            'content_type' => 'general',
            'body' => 'Body text.',
        ]);

        // church_memberships.user_id is restrictOnDelete (Blueprint v1.4.1
        // preflight decision #7) — a membership is the access grant
        // itself, not attribution metadata. ChurchMembership::remove()
        // only changes status (kept for audit history), which does not
        // clear the FK; the membership row itself must be gone before the
        // account can be deleted. This mirrors the real product ordering
        // the same decision specifies ("remove membership... before any
        // account deletion").
        $user->memberships()->first()->delete();
        $user->delete();

        $this->assertNull($contentItem->fresh()->created_by);
        $this->assertNull($contentItem->fresh()->updated_by);
    }
}
