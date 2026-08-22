<?php

namespace Tests\Feature\Campaigns;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Enums\CampaignStatus;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Enums\ContentStatus;
use App\Filament\Pages\Campaigns as CampaignsPage;
use App\Models\Campaign;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CampaignPlanningListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $church = Church::create(['name' => 'Campaign List Church', 'slug' => 'campaign-list-church']);
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
    }

    public function test_empty_state_explains_campaign_planning_and_offers_creation(): void
    {
        Livewire::test(CampaignsPage::class)
            ->assertSuccessful()
            ->assertSee('Plan your first Campaign')
            ->assertSee('coordinate the messages needed')
            ->assertActionVisible('createCampaign');
    }

    public function test_create_campaign_action_creates_a_draft_and_redirects_to_workspace(): void
    {
        Livewire::test(CampaignsPage::class)
            ->callAction('createCampaign', data: [
                'title' => 'Easter 2028',
                'purpose' => 'Coordinate Easter communication.',
                'starts_on' => '2028-03-20',
                'ends_on' => '2028-04-02',
            ])
            ->assertHasNoActionErrors();

        $campaign = Campaign::query()->sole();
        $this->assertSame(CampaignStatus::DRAFT, $campaign->status);
        $this->assertSame('Easter 2028', $campaign->title);
    }

    public function test_list_separates_lifecycle_sections_and_eager_loads_readiness(): void
    {
        $manager = app(CampaignManager::class);
        $draft = $manager->create(['title' => 'Draft Initiative']);
        $planned = $manager->create(['title' => 'Planned Initiative']);
        $manager->transition($planned, CampaignStatus::PLANNED);
        $active = $manager->create(['title' => 'Active Initiative']);
        $manager->transition($active, CampaignStatus::PLANNED);
        $manager->transition($active, CampaignStatus::ACTIVE);
        $completed = $manager->create(['title' => 'Completed Initiative']);
        $manager->transition($completed, CampaignStatus::PLANNED);
        $manager->transition($completed, CampaignStatus::ACTIVE);
        $manager->transition($completed, CampaignStatus::COMPLETED);
        $archived = $manager->create(['title' => 'Archived Initiative']);
        $manager->transition($archived, CampaignStatus::PLANNED);
        $manager->transition($archived, CampaignStatus::ACTIVE);
        $manager->transition($archived, CampaignStatus::COMPLETED);
        $manager->transition($archived, CampaignStatus::ARCHIVED);

        $component = Livewire::test(CampaignsPage::class)
            ->assertSee('Active Initiative')
            ->assertSee('Planned Initiative')
            ->assertSee('Draft Initiative')
            ->assertSee('Completed Initiative')
            ->assertSee('Archived Initiative');

        $sections = $component->instance()->campaignSections();
        $this->assertSame([$active->id], $sections['Active']->pluck('id')->all());
        $this->assertSame([$archived->id], $sections['Archived']->pluck('id')->all());
        $this->assertTrue($sections['Active']->first()->relationLoaded('communications'));
    }

    public function test_readiness_counts_are_derived_and_cancelled_entries_are_excluded(): void
    {
        $campaign = app(CampaignManager::class)->create(['title' => 'Readiness Campaign']);
        $communicationManager = app(CampaignCommunicationManager::class);

        $ready = $communicationManager->add($campaign, ['title' => 'Ready', 'channel' => CommunicationChannel::GENERAL]);
        $approved = ContentItem::create(['title' => 'Approved', 'content_type' => 'campaign_copy', 'body' => 'Body']);
        $approved->forceFill(['status' => ContentStatus::APPROVED])->save();
        $communicationManager->linkContentItem($ready, $approved);

        $notStarted = $communicationManager->add($campaign, ['title' => 'Not started', 'channel' => CommunicationChannel::EMAIL]);
        $cancelled = $communicationManager->add($campaign, ['title' => 'Cancelled', 'channel' => CommunicationChannel::SMS]);
        $communicationManager->cancel($cancelled);

        $component = Livewire::test(CampaignsPage::class);
        $counts = $component->instance()->readinessCounts($campaign->fresh()->load('communications.contentItem'));

        $this->assertSame(2, $counts['total']);
        $this->assertSame(1, $counts['prepared']);
        $this->assertSame(1, $counts['not_started']);
        $this->assertSame(0, $counts['outstanding']);
        $this->assertNotNull($notStarted);
    }
}
