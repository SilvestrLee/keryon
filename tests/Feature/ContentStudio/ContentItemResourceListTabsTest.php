<?php

namespace Tests\Feature\ContentStudio;

use App\Enums\ChurchRole;
use App\Enums\ContentStatus;
use App\Filament\Resources\ContentItemResource\Pages\ListContentItems;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContentItemResourceListTabsTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $this->user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->user);
    }

    /**
     * Create a ContentItem and move it straight to the given status via a raw
     * query update (bypassing the workflow methods' transition guards and the
     * model's dirty-field hooks) — this test only needs fixture data in a known
     * state, not to exercise the lifecycle rules themselves.
     */
    protected function makeContentItem(ContentStatus $status, string $title): ContentItem
    {
        $item = ContentItem::create([
            'title' => $title,
            'content_type' => 'general',
            'body' => 'Body text.',
        ]);

        ContentItem::query()->whereKey($item->getKey())->update(['status' => $status->value]);

        return $item->fresh();
    }

    public function test_all_tab_is_the_default_and_shows_every_status(): void
    {
        $draft = $this->makeContentItem(ContentStatus::DRAFT, 'Draft Item');
        $review = $this->makeContentItem(ContentStatus::REVIEW, 'Review Item');
        $rejected = $this->makeContentItem(ContentStatus::REJECTED, 'Rejected Item');
        $approved = $this->makeContentItem(ContentStatus::APPROVED, 'Approved Item');

        Livewire::test(ListContentItems::class)
            ->assertSet('activeTab', 'all')
            ->assertCanSeeTableRecords([$draft, $review, $rejected, $approved])
            ->assertCountTableRecords(4);
    }

    public function test_each_status_tab_shows_only_matching_records(): void
    {
        $draft = $this->makeContentItem(ContentStatus::DRAFT, 'Draft Item');
        $review = $this->makeContentItem(ContentStatus::REVIEW, 'Review Item');
        $rejected = $this->makeContentItem(ContentStatus::REJECTED, 'Rejected Item');
        $approved = $this->makeContentItem(ContentStatus::APPROVED, 'Approved Item');

        Livewire::test(ListContentItems::class)
            ->set('activeTab', 'draft')
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$review, $rejected, $approved]);

        Livewire::test(ListContentItems::class)
            ->set('activeTab', 'review')
            ->assertCanSeeTableRecords([$review])
            ->assertCanNotSeeTableRecords([$draft, $rejected, $approved]);

        Livewire::test(ListContentItems::class)
            ->set('activeTab', 'rejected')
            ->assertCanSeeTableRecords([$rejected])
            ->assertCanNotSeeTableRecords([$draft, $review, $approved]);

        Livewire::test(ListContentItems::class)
            ->set('activeTab', 'approved')
            ->assertCanSeeTableRecords([$approved])
            ->assertCanNotSeeTableRecords([$draft, $review, $rejected]);
    }

    public function test_needs_changes_tab_is_labelled_from_the_canonical_status_not_the_raw_enum_value(): void
    {
        $tabs = Livewire::test(ListContentItems::class)->instance()->getTabs();

        $this->assertSame(ContentStatus::REJECTED->label(), $tabs['rejected']->getLabel());
        $this->assertNotSame('rejected', $tabs['rejected']->getLabel());
    }

    public function test_tab_badges_count_only_matching_records(): void
    {
        $this->makeContentItem(ContentStatus::DRAFT, 'Draft One');
        $this->makeContentItem(ContentStatus::DRAFT, 'Draft Two');
        $this->makeContentItem(ContentStatus::REVIEW, 'Review Item');

        $tabs = Livewire::test(ListContentItems::class)->instance()->getTabs();

        $this->assertSame(2, (int) $tabs['draft']->getBadge());
        $this->assertSame(1, (int) $tabs['review']->getBadge());
        $this->assertSame(0, (int) $tabs['approved']->getBadge());
        $this->assertSame(3, (int) $tabs['all']->getBadge());
    }

    public function test_table_renders_without_error_for_empty_and_markdown_heavy_body_content(): void
    {
        $withMarkdown = $this->makeContentItem(ContentStatus::DRAFT, 'Rich Body Item');
        ContentItem::query()->whereKey($withMarkdown->getKey())->update([
            'body' => str_repeat('Lorem ipsum dolor sit amet. ', 40)."\n\n# Heading\n\n- one\n- two\n- three",
        ]);

        $withEmptyBody = $this->makeContentItem(ContentStatus::DRAFT, 'Empty Body Item');
        ContentItem::query()->whereKey($withEmptyBody->getKey())->update(['body' => '']);

        Livewire::test(ListContentItems::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$withMarkdown->fresh(), $withEmptyBody->fresh()]);
    }

    public function test_page_identifies_itself_as_content_studio(): void
    {
        $component = Livewire::test(ListContentItems::class);

        $this->assertSame('Content Studio', $component->instance()->getTitle());
        $this->assertSame(
            'Create, review and prepare your church communications.',
            $component->instance()->getSubheading()
        );
    }

    public function test_empty_state_copy_is_specific_to_the_active_tab(): void
    {
        Livewire::test(ListContentItems::class)
            ->set('activeTab', 'draft')
            ->assertSeeText('No drafts yet');

        Livewire::test(ListContentItems::class)
            ->set('activeTab', 'review')
            ->assertSeeText('Nothing awaiting review');

        Livewire::test(ListContentItems::class)
            ->set('activeTab', 'rejected')
            ->assertSeeText('Nothing needs changes');

        Livewire::test(ListContentItems::class)
            ->set('activeTab', 'approved')
            ->assertSeeText('No approved content yet');

        Livewire::test(ListContentItems::class)
            ->assertSeeText('No content yet');
    }

    public function test_tabs_and_badges_remain_tenant_scoped(): void
    {
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b']);
        $userB = User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($userB);
        $this->makeContentItem(ContentStatus::DRAFT, 'Church B Draft');
        $this->makeContentItem(ContentStatus::APPROVED, 'Church B Approved');

        $this->actingAs($this->user);
        $churchADraft = $this->makeContentItem(ContentStatus::DRAFT, 'Church A Draft');

        $tabs = Livewire::test(ListContentItems::class)->instance()->getTabs();

        // Church B's two records must never leak into Church A's tabs or badges.
        $this->assertSame(1, (int) $tabs['draft']->getBadge());
        $this->assertSame(0, (int) $tabs['approved']->getBadge());
        $this->assertSame(1, (int) $tabs['all']->getBadge());

        Livewire::test(ListContentItems::class)
            ->set('activeTab', 'draft')
            ->assertCanSeeTableRecords([$churchADraft])
            ->assertCountTableRecords(1);
    }
}
