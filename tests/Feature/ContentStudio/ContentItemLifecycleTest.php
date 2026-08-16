<?php

namespace Tests\Feature\ContentStudio;

use App\Enums\ContentStatus;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContentItemLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $this->user = User::factory()->create(['church_id' => $church->id]);
        $this->actingAs($this->user);
    }

    protected function makeDraft(): ContentItem
    {
        return ContentItem::create([
            'title' => 'Draft Content',
            'content_type' => 'general',
            'body' => 'Original body.',
        ]);
    }

    public function test_draft_can_submit_for_review(): void
    {
        $contentItem = $this->makeDraft();

        $contentItem->submitForReview($this->user);

        $this->assertSame(ContentStatus::REVIEW, $contentItem->fresh()->status);
    }

    public function test_draft_cannot_approve_directly(): void
    {
        $contentItem = $this->makeDraft();

        $this->expectException(LogicException::class);

        $contentItem->approve($this->user);
    }

    public function test_review_can_approve(): void
    {
        $contentItem = $this->makeDraft();
        $contentItem->submitForReview($this->user);

        $contentItem->approve($this->user);

        $this->assertSame(ContentStatus::APPROVED, $contentItem->fresh()->status);
    }

    public function test_approval_records_approver_and_time(): void
    {
        $contentItem = $this->makeDraft();
        $contentItem->submitForReview($this->user);

        $contentItem->approve($this->user);
        $fresh = $contentItem->fresh();

        $this->assertSame($this->user->id, $fresh->approved_by);
        $this->assertNotNull($fresh->approved_at);
    }

    public function test_review_can_request_changes_with_reason(): void
    {
        $contentItem = $this->makeDraft();
        $contentItem->submitForReview($this->user);

        $contentItem->requestChanges($this->user, 'Please shorten the second paragraph.');
        $fresh = $contentItem->fresh();

        $this->assertSame(ContentStatus::REJECTED, $fresh->status);
        $this->assertSame('Please shorten the second paragraph.', $fresh->rejection_reason);
    }

    public function test_missing_rejection_reason_is_rejected(): void
    {
        $contentItem = $this->makeDraft();
        $contentItem->submitForReview($this->user);

        $this->expectException(InvalidArgumentException::class);

        $contentItem->requestChanges($this->user, '   ');
    }

    public function test_rejected_item_can_return_to_draft(): void
    {
        $contentItem = $this->makeDraft();
        $contentItem->submitForReview($this->user);
        $contentItem->requestChanges($this->user, 'Needs a scripture reference.');

        $contentItem->returnToDraft($this->user);

        $this->assertSame(ContentStatus::DRAFT, $contentItem->fresh()->status);
    }

    public function test_rejection_feedback_remains_visible_during_revision(): void
    {
        $contentItem = $this->makeDraft();
        $contentItem->submitForReview($this->user);
        $contentItem->requestChanges($this->user, 'Needs a scripture reference.');

        $contentItem->returnToDraft($this->user);

        $this->assertSame('Needs a scripture reference.', $contentItem->fresh()->rejection_reason);
    }

    public function test_resubmission_clears_previous_rejection_reason(): void
    {
        $contentItem = $this->makeDraft();
        $contentItem->submitForReview($this->user);
        $contentItem->requestChanges($this->user, 'Needs a scripture reference.');
        $contentItem->returnToDraft($this->user);

        $contentItem->submitForReview($this->user);

        $this->assertNull($contentItem->fresh()->rejection_reason);
    }

    public function test_approval_clears_rejection_reason(): void
    {
        $contentItem = $this->makeDraft();
        $contentItem->submitForReview($this->user);
        $contentItem->requestChanges($this->user, 'Needs a scripture reference.');
        $contentItem->returnToDraft($this->user);
        $contentItem->submitForReview($this->user);

        $contentItem->approve($this->user);

        $this->assertNull($contentItem->fresh()->rejection_reason);
    }

    public function test_approved_cannot_be_rejected_without_returning_to_review(): void
    {
        $contentItem = $this->makeDraft();
        $contentItem->submitForReview($this->user);
        $contentItem->approve($this->user);

        $this->expectException(LogicException::class);

        $contentItem->requestChanges($this->user, 'Too late.');
    }
}
