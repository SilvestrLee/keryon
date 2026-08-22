<?php

namespace Tests\Feature\ContentStudio;

use App\Enums\ContentStatus;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContentItemApprovalInvalidationTest extends TestCase
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

    protected function makeApproved(): ContentItem
    {
        $contentItem = ContentItem::create([
            'title' => 'Approved Content',
            'content_type' => 'general',
            'body' => 'Original body.',
        ]);
        $contentItem->submitForReview($this->user);
        $contentItem->approve($this->user);

        return $contentItem->fresh();
    }

    public function test_editing_title_of_approved_content_returns_it_to_draft(): void
    {
        $contentItem = $this->makeApproved();

        $contentItem->update(['title' => 'Revised Title']);

        $this->assertSame(ContentStatus::DRAFT, $contentItem->fresh()->status);
    }

    public function test_editing_body_of_approved_content_returns_it_to_draft(): void
    {
        $contentItem = $this->makeApproved();

        $contentItem->update(['body' => 'Revised body text.']);

        $this->assertSame(ContentStatus::DRAFT, $contentItem->fresh()->status);
    }

    public function test_changing_content_type_of_approved_content_returns_it_to_draft(): void
    {
        $contentItem = $this->makeApproved();

        $contentItem->update(['content_type' => 'announcement']);

        $this->assertSame(ContentStatus::DRAFT, $contentItem->fresh()->status);
    }

    public function test_editing_approved_content_clears_approval_metadata(): void
    {
        $contentItem = $this->makeApproved();

        $contentItem->update(['title' => 'Revised Title']);
        $fresh = $contentItem->fresh();

        $this->assertNull($fresh->approved_by);
        $this->assertNull($fresh->approved_at);
    }

    public function test_saving_approved_content_without_content_changes_does_not_invalidate_approval(): void
    {
        $contentItem = $this->makeApproved();

        // Re-saving unrelated framework-managed state (e.g. touching the
        // model) must not spuriously invalidate approval — only the
        // content fields themselves (title/content_type/body) should.
        $contentItem->touch();

        $fresh = $contentItem->fresh();
        $this->assertSame(ContentStatus::APPROVED, $fresh->status);
        $this->assertNotNull($fresh->approved_by);
        $this->assertNotNull($fresh->approved_at);
    }

    public function test_updated_by_changes_on_meaningful_content_update(): void
    {
        $contentItem = $this->makeApproved();
        $otherChurchUser = $this->user;

        $contentItem->update(['body' => 'A meaningfully different body.']);

        $this->assertSame($otherChurchUser->id, $contentItem->fresh()->updated_by);
    }
}
