<?php

namespace Tests\Feature\ContentStudio;

use App\Enums\ContentOrigin;
use App\Enums\ContentStatus;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContentItemCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $this->user = User::factory()->forChurch($church)->create();
        $this->actingAs($this->user);
    }

    public function test_authenticated_church_user_can_create_content_item(): void
    {
        $contentItem = ContentItem::create([
            'title' => 'Sunday Service Follow-up — 16 August',
            'content_type' => 'announcement',
            'body' => 'Thank you for joining us this Sunday.',
        ]);

        $this->assertDatabaseHas('content_items', [
            'id' => $contentItem->id,
            'title' => 'Sunday Service Follow-up — 16 August',
        ]);
    }

    public function test_church_id_is_assigned_server_side(): void
    {
        $contentItem = ContentItem::create([
            'title' => 'Youth Week Announcement',
            'content_type' => 'announcement',
            'body' => 'Body text.',
        ]);

        $this->assertSame($this->user->church_id, $contentItem->church_id);
    }

    public function test_created_by_is_assigned(): void
    {
        $contentItem = ContentItem::create([
            'title' => 'Prayer Focus — September',
            'content_type' => 'prayer_points',
            'body' => 'Body text.',
        ]);

        $this->assertSame($this->user->id, $contentItem->created_by);
    }

    public function test_updated_by_is_assigned_on_create(): void
    {
        $contentItem = ContentItem::create([
            'title' => 'Sunday Sermon Social Captions',
            'content_type' => 'social_caption',
            'body' => 'Body text.',
        ]);

        $this->assertSame($this->user->id, $contentItem->updated_by);
    }

    public function test_origin_defaults_to_human(): void
    {
        $contentItem = ContentItem::create([
            'title' => 'Discussion Guide',
            'content_type' => 'discussion_questions',
            'body' => 'Body text.',
        ]);

        $this->assertSame(ContentOrigin::HUMAN, $contentItem->origin);
    }

    public function test_status_defaults_to_draft(): void
    {
        $contentItem = ContentItem::create([
            'title' => 'Campaign Copy Draft',
            'content_type' => 'campaign_copy',
            'body' => 'Body text.',
        ]);

        $this->assertSame(ContentStatus::DRAFT, $contentItem->status);
    }

    public function test_church_id_is_not_mass_assignable(): void
    {
        $otherChurch = Church::create(['name' => 'Other Church', 'slug' => 'other-church']);

        $contentItem = ContentItem::create([
            'church_id' => $otherChurch->id,
            'title' => 'Attempted Cross-Church Assignment',
            'content_type' => 'general',
            'body' => 'Body text.',
        ]);

        $this->assertSame($this->user->church_id, $contentItem->church_id);
        $this->assertNotSame($otherChurch->id, $contentItem->church_id);
    }
}
