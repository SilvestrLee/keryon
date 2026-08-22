<?php

namespace Tests\Feature\ContentStudio;

use App\Enums\ChurchRole;
use App\Enums\ContentOrigin;
use App\Enums\ContentStatus;
use App\Filament\Resources\ContentItemResource\Pages\ListContentItems;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContentItemResourceCreateTest extends TestCase
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

    public function test_create_action_creates_a_content_item(): void
    {
        Livewire::test(ListContentItems::class)
            ->callAction('create', data: [
                'title' => 'Sunday Sermon Follow-up — 16 August',
                'content_type' => 'announcement',
                'body' => 'Thank you for joining us this Sunday.',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('content_items', [
            'title' => 'Sunday Sermon Follow-up — 16 August',
        ]);
    }

    public function test_created_content_defaults_to_draft_status(): void
    {
        Livewire::test(ListContentItems::class)
            ->callAction('create', data: [
                'title' => 'New Content',
                'content_type' => 'general',
                'body' => 'Body text.',
            ]);

        $contentItem = ContentItem::query()->latest('id')->first();

        $this->assertSame(ContentStatus::DRAFT, $contentItem->status);
    }

    public function test_created_content_defaults_to_human_origin(): void
    {
        Livewire::test(ListContentItems::class)
            ->callAction('create', data: [
                'title' => 'New Content',
                'content_type' => 'general',
                'body' => 'Body text.',
            ]);

        $contentItem = ContentItem::query()->latest('id')->first();

        $this->assertSame(ContentOrigin::HUMAN, $contentItem->origin);
    }

    public function test_created_content_is_assigned_to_the_authenticated_users_church(): void
    {
        Livewire::test(ListContentItems::class)
            ->callAction('create', data: [
                'title' => 'New Content',
                'content_type' => 'general',
                'body' => 'Body text.',
            ]);

        $contentItem = ContentItem::query()->latest('id')->first();

        $this->assertSame($this->user->church_id, $contentItem->church_id);
    }

    public function test_title_is_required(): void
    {
        Livewire::test(ListContentItems::class)
            ->callAction('create', data: [
                'title' => '',
                'content_type' => 'general',
                'body' => 'Body text.',
            ])
            ->assertHasActionErrors(['title' => 'required']);
    }

    public function test_body_is_required(): void
    {
        Livewire::test(ListContentItems::class)
            ->callAction('create', data: [
                'title' => 'New Content',
                'content_type' => 'general',
                'body' => '',
            ])
            ->assertHasActionErrors(['body' => 'required']);
    }
}
