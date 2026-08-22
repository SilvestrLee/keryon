<?php

namespace Tests\Feature\ContentStudio;

use App\Enums\ChurchRole;
use App\Enums\ContentStatus;
use App\Filament\Resources\ContentItemResource\Pages\EditContentItem;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContentItemResourceEditTest extends TestCase
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

    public function test_normal_draft_editing_saves_changes(): void
    {
        $contentItem = ContentItem::create([
            'title' => 'Original Title',
            'content_type' => 'general',
            'body' => 'Original body.',
        ]);

        Livewire::test(EditContentItem::class, ['record' => $contentItem->getRouteKey()])
            ->fillForm([
                'title' => 'Revised Title',
                'content_type' => 'announcement',
                'body' => 'Revised body.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $contentItem->fresh();
        $this->assertSame('Revised Title', $fresh->title);
        $this->assertSame(ContentStatus::DRAFT, $fresh->status);
    }

    public function test_editing_approved_content_resets_it_to_draft(): void
    {
        $contentItem = ContentItem::create([
            'title' => 'Original Title',
            'content_type' => 'general',
            'body' => 'Original body.',
        ]);
        $contentItem->submitForReview($this->user);
        $contentItem->approve($this->user);

        Livewire::test(EditContentItem::class, ['record' => $contentItem->getRouteKey()])
            ->fillForm([
                'body' => 'Revised after approval.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $contentItem->fresh();
        $this->assertSame(ContentStatus::DRAFT, $fresh->status);
        $this->assertNull($fresh->approved_by);
        $this->assertNull($fresh->approved_at);
    }

    public function test_save_form_action_is_labelled_save_draft(): void
    {
        // The save action is a special form-footer action, not part of the
        // page's registered Actions schema — assertActionHasLabel() can't
        // see it. Verified directly via the page's own override instead.
        $page = app(EditContentItem::class);
        $label = (new \ReflectionMethod($page, 'getSaveFormAction'))
            ->invoke($page)
            ->getLabel();

        $this->assertSame('Save Draft', $label);
    }
}
