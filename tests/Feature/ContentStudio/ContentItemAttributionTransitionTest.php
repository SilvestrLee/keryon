<?php

namespace Tests\Feature\ContentStudio;

use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Proves updated_by reflects the acting user for every meaningful
// mutation — content edits AND workflow actions — per K-CONTENT-002
// Final Fix §2-§7. Two same-church users, no new roles.
class ContentItemAttributionTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;

    protected User $userB;

    protected ContentItem $contentItem;

    protected function setUp(): void
    {
        parent::setUp();

        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $this->userA = User::factory()->forChurch($church)->create();
        $this->userB = User::factory()->forChurch($church)->create();

        $this->actingAs($this->userA);
        $this->contentItem = ContentItem::create([
            'title' => 'Original Title',
            'content_type' => 'general',
            'body' => 'Original body.',
        ]);
    }

    public function test_editing_content_by_a_different_user_updates_attribution(): void
    {
        $this->actingAs($this->userB);

        $this->contentItem->update(['body' => 'Revised by a different user.']);

        $this->assertSame($this->userB->id, $this->contentItem->fresh()->updated_by);
    }

    public function test_submitting_for_review_by_a_different_user_updates_attribution(): void
    {
        $this->actingAs($this->userB);

        $this->contentItem->submitForReview($this->userB);

        $this->assertSame($this->userB->id, $this->contentItem->fresh()->updated_by);
    }

    public function test_requesting_changes_by_a_different_user_updates_attribution(): void
    {
        $this->contentItem->submitForReview($this->userA);

        $this->actingAs($this->userB);
        $this->contentItem->requestChanges($this->userB, 'Please revise the opening line.');

        $this->assertSame($this->userB->id, $this->contentItem->fresh()->updated_by);
    }

    public function test_approving_by_a_different_user_updates_both_approved_by_and_updated_by(): void
    {
        $this->contentItem->submitForReview($this->userA);

        $this->actingAs($this->userB);
        $this->contentItem->approve($this->userB);
        $fresh = $this->contentItem->fresh();

        $this->assertSame($this->userB->id, $fresh->approved_by);
        $this->assertSame($this->userB->id, $fresh->updated_by);
    }

    public function test_returning_to_draft_by_a_different_user_updates_attribution(): void
    {
        $this->contentItem->submitForReview($this->userA);
        $this->contentItem->requestChanges($this->userA, 'Needs work.');

        $this->actingAs($this->userB);
        $this->contentItem->returnToDraft($this->userB);

        $this->assertSame($this->userB->id, $this->contentItem->fresh()->updated_by);
    }

    public function test_created_by_remains_the_original_creator_throughout(): void
    {
        $this->contentItem->submitForReview($this->userA);

        $this->actingAs($this->userB);
        $this->contentItem->approve($this->userB);

        // created_by is never reassigned by any lifecycle action —
        // only updated_by/approved_by move with the acting user.
        $this->assertSame($this->userA->id, $this->contentItem->fresh()->created_by);
    }
}
