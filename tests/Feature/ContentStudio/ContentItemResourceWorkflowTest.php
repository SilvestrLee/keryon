<?php

namespace Tests\Feature\ContentStudio;

use App\Enums\ChurchRole;
use App\Enums\ContentStatus;
use App\Filament\Resources\ContentItemResource\Pages\ViewContentItem;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContentItemResourceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $this->user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
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

    public function test_submit_for_review_action_transitions_via_domain_method(): void
    {
        $contentItem = $this->makeDraft();

        Livewire::test(ViewContentItem::class, ['record' => $contentItem->getRouteKey()])
            ->callAction('submitForReview');

        $fresh = $contentItem->fresh();
        $this->assertSame(ContentStatus::REVIEW, $fresh->status);
        $this->assertSame($this->user->id, $fresh->updated_by);
    }

    public function test_approve_action_is_only_available_in_review(): void
    {
        $contentItem = $this->makeDraft();

        Livewire::test(ViewContentItem::class, ['record' => $contentItem->getRouteKey()])
            ->assertActionHidden('approve');
    }

    public function test_approve_action_records_approver_and_updated_by(): void
    {
        $contentItem = $this->makeDraft();
        $contentItem->submitForReview($this->user);

        Livewire::test(ViewContentItem::class, ['record' => $contentItem->getRouteKey()])
            ->callAction('approve');

        $fresh = $contentItem->fresh();
        $this->assertSame(ContentStatus::APPROVED, $fresh->status);
        $this->assertSame($this->user->id, $fresh->approved_by);
        $this->assertSame($this->user->id, $fresh->updated_by);
    }

    public function test_request_changes_requires_a_reason(): void
    {
        $contentItem = $this->makeDraft();
        $contentItem->submitForReview($this->user);

        Livewire::test(ViewContentItem::class, ['record' => $contentItem->getRouteKey()])
            ->callAction('requestChanges', data: ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);
    }

    public function test_request_changes_stores_feedback_and_sets_needs_changes(): void
    {
        $contentItem = $this->makeDraft();
        $contentItem->submitForReview($this->user);

        Livewire::test(ViewContentItem::class, ['record' => $contentItem->getRouteKey()])
            ->callAction('requestChanges', data: ['reason' => 'Please shorten the second paragraph.'])
            ->assertHasNoActionErrors();

        $fresh = $contentItem->fresh();
        $this->assertSame(ContentStatus::REJECTED, $fresh->status);
        $this->assertSame('Please shorten the second paragraph.', $fresh->rejection_reason);
        $this->assertSame($this->user->id, $fresh->updated_by);
    }

    public function test_return_to_draft_action_is_available_when_needs_changes(): void
    {
        $contentItem = $this->makeDraft();
        $contentItem->submitForReview($this->user);
        $contentItem->requestChanges($this->user, 'Needs a scripture reference.');

        Livewire::test(ViewContentItem::class, ['record' => $contentItem->getRouteKey()])
            ->callAction('returnToDraft');

        $fresh = $contentItem->fresh();
        $this->assertSame(ContentStatus::DRAFT, $fresh->status);
        $this->assertSame($this->user->id, $fresh->updated_by);
    }

    public function test_resubmission_clears_stale_rejection_feedback(): void
    {
        $contentItem = $this->makeDraft();
        $contentItem->submitForReview($this->user);
        $contentItem->requestChanges($this->user, 'Needs a scripture reference.');
        $contentItem->returnToDraft($this->user);

        Livewire::test(ViewContentItem::class, ['record' => $contentItem->getRouteKey()])
            ->callAction('submitForReview');

        $this->assertNull($contentItem->fresh()->rejection_reason);
    }

    public function test_return_to_draft_action_is_not_available_for_a_plain_draft(): void
    {
        $contentItem = $this->makeDraft();

        Livewire::test(ViewContentItem::class, ['record' => $contentItem->getRouteKey()])
            ->assertActionHidden('returnToDraft');
    }
}
