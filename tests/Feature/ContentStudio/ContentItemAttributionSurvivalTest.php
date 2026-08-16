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
        $user = User::factory()->create(['church_id' => $church->id]);
        $this->actingAs($user);

        $contentItem = ContentItem::create([
            'title' => 'Content Surviving User Removal',
            'content_type' => 'general',
            'body' => 'Body text.',
        ]);

        $user->delete();

        $this->assertDatabaseHas('content_items', ['id' => $contentItem->id]);
    }

    public function test_attribution_fk_becomes_null_when_user_is_removed(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->create(['church_id' => $church->id]);
        $this->actingAs($user);

        $contentItem = ContentItem::create([
            'title' => 'Content Surviving User Removal',
            'content_type' => 'general',
            'body' => 'Body text.',
        ]);

        $user->delete();

        $this->assertNull($contentItem->fresh()->created_by);
        $this->assertNull($contentItem->fresh()->updated_by);
    }
}
