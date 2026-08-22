<?php

namespace Tests\Feature\Campaigns;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Enums\CampaignStatus;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Enums\ContentStatus;
use App\Filament\Pages\CampaignWorkspace;
use App\Models\Campaign;
use App\Models\CampaignCommunication;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CampaignWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private CampaignManager $campaigns;

    private CampaignCommunicationManager $communications;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $church = Church::create(['name' => 'Campaign Workspace Church', 'slug' => 'campaign-workspace-church']);
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        $this->campaigns = app(CampaignManager::class);
        $this->communications = app(CampaignCommunicationManager::class);
    }

    private function campaign(string $title = 'Workspace Campaign'): Campaign
    {
        return $this->campaigns->create(['title' => $title, 'purpose' => 'Coordinate the initiative.']);
    }

    private function content(string $title, ContentStatus $status): ContentItem
    {
        $content = ContentItem::create(['title' => $title, 'content_type' => 'campaign_copy', 'body' => 'Canonical body']);
        $content->forceFill(['status' => $status])->save();

        return $content;
    }

    public function test_workspace_orders_plan_displays_content_state_and_upcoming_targets(): void
    {
        $campaign = $this->campaign();
        $later = $this->communications->add($campaign, [
            'title' => 'Later WhatsApp reminder',
            'channel' => CommunicationChannel::WHATSAPP,
            'target_at' => now()->addDays(4),
            'sort_order' => 1,
        ]);
        $first = $this->communications->add($campaign, [
            'title' => 'First Instagram announcement',
            'channel' => CommunicationChannel::INSTAGRAM,
            'target_at' => now()->addDays(2),
            'sort_order' => 0,
        ]);
        $this->communications->linkContentItem($first, $this->content('Approved Easter copy', ContentStatus::APPROVED));

        Livewire::test(CampaignWorkspace::class, ['campaign' => $campaign->id])
            ->assertSuccessful()
            ->assertSeeInOrder(['First Instagram announcement', 'Later WhatsApp reminder'])
            ->assertSee('Approved Easter copy')
            ->assertSee('Content Studio · Approved')
            ->assertSeeInOrder([$first->target_at->format('j M · H:i'), $later->target_at->format('j M · H:i')]);
    }

    public function test_add_edit_link_unlink_cancel_restore_and_reorder_use_domain_behavior(): void
    {
        $campaign = $this->campaign();
        $content = $this->content('Existing Content', ContentStatus::REVIEW);
        $component = Livewire::test(CampaignWorkspace::class, ['campaign' => $campaign->id]);

        $component->callAction('addCommunication', data: [
            'title' => 'Primary announcement',
            'purpose' => 'Introduce the initiative.',
            'channel' => CommunicationChannel::FACEBOOK->value,
            'target_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'content_item_id' => $content->id,
        ])->assertHasNoActionErrors();

        $first = CampaignCommunication::query()->sole();
        $this->assertSame($content->id, $first->content_item_id);
        $this->assertSame('awaiting_approval', $first->readiness());

        $component->callAction('editCommunication', data: [
            'title' => 'Updated announcement',
            'purpose' => 'Updated purpose.',
            'channel' => CommunicationChannel::EMAIL->value,
            'target_at' => null,
            'content_item_id' => $content->id,
        ], arguments: ['communication' => $first->id])->assertHasNoActionErrors();
        $this->assertSame('Updated announcement', $first->fresh()->title);

        $component->callAction('unlinkCommunicationContent', arguments: ['communication' => $first->id]);
        $this->assertNull($first->fresh()->content_item_id);
        $this->assertDatabaseHas('content_items', ['id' => $content->id, 'deleted_at' => null]);

        $second = $this->communications->add($campaign, ['title' => 'Second', 'channel' => CommunicationChannel::GENERAL, 'sort_order' => 1]);
        $component->call('moveCommunication', $second->id, 'up');
        $this->assertSame([$second->id, $first->id], $campaign->fresh()->communications()->pluck('id')->all());

        $component->callAction('cancelCommunication', arguments: ['communication' => $first->id]);
        $this->assertNotNull($first->fresh()->cancelled_at);
        $component->callAction('restoreCommunication', arguments: ['communication' => $first->id]);
        $this->assertNull($first->fresh()->cancelled_at);
    }

    public function test_lifecycle_actions_expose_only_valid_transitions_and_confirmation_is_real(): void
    {
        $campaign = $this->campaign();

        Livewire::test(CampaignWorkspace::class, ['campaign' => $campaign->id])
            ->assertActionVisible('planCampaign')
            ->assertActionHidden('activateCampaign')
            ->callAction('planCampaign');
        $this->assertSame(CampaignStatus::PLANNED, $campaign->fresh()->status);

        Livewire::test(CampaignWorkspace::class, ['campaign' => $campaign->id])
            ->assertActionVisible('activateCampaign')
            ->callAction('activateCampaign');
        $this->assertSame(CampaignStatus::ACTIVE, $campaign->fresh()->status);

        $activeComponent = Livewire::test(CampaignWorkspace::class, ['campaign' => $campaign->id])
            ->assertActionVisible('returnToPlanned')
            ->mountAction('returnToPlanned');
        $this->assertSame(CampaignStatus::ACTIVE, $campaign->fresh()->status);

        $activeComponent->callMountedAction();
        $this->assertSame(CampaignStatus::PLANNED, $campaign->fresh()->status);
    }

    public function test_complete_archive_and_draft_delete_actions_follow_domain_rules(): void
    {
        $campaign = $this->campaign('Lifecycle completion');
        $this->campaigns->transition($campaign, CampaignStatus::PLANNED);
        $this->campaigns->transition($campaign, CampaignStatus::ACTIVE);

        Livewire::test(CampaignWorkspace::class, ['campaign' => $campaign->id])->callAction('completeCampaign');
        $this->assertSame(CampaignStatus::COMPLETED, $campaign->fresh()->status);
        Livewire::test(CampaignWorkspace::class, ['campaign' => $campaign->id])->callAction('archiveCampaign');
        $this->assertSame(CampaignStatus::ARCHIVED, $campaign->fresh()->status);

        $draft = $this->campaign('Delete this Draft');
        $content = $this->content('Content survives', ContentStatus::APPROVED);
        $communication = $this->communications->add($draft, ['title' => 'Draft communication', 'channel' => CommunicationChannel::GENERAL]);
        $this->communications->linkContentItem($communication, $content);

        Livewire::test(CampaignWorkspace::class, ['campaign' => $draft->id])->callAction('deleteCampaign');
        $this->assertSoftDeleted('campaigns', ['id' => $draft->id]);
        $this->assertDatabaseHas('content_items', ['id' => $content->id, 'deleted_at' => null]);
    }

    public function test_cross_church_campaign_and_content_never_appear(): void
    {
        $campaignA = $this->campaign('Church A Campaign');

        $churchB = Church::create(['name' => 'Other Workspace Church', 'slug' => 'other-workspace-church']);
        $this->actingAs(User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();
        $contentB = $this->content('Church B private content', ContentStatus::APPROVED);

        $this->actingAs($campaignA->creator);
        app(TenantContext::class)->forgetResolved();

        Livewire::test(CampaignWorkspace::class, ['campaign' => $campaignA->id])
            ->assertDontSee('Church B private content');

        Livewire::test(CampaignWorkspace::class, ['campaign' => $campaignA->id])
            ->callAction('addCommunication', data: [
                'title' => 'Injected foreign content',
                'purpose' => null,
                'channel' => CommunicationChannel::GENERAL->value,
                'target_at' => null,
                'content_item_id' => $contentB->id,
            ])
            ->assertHasActionErrors(['content_item_id']);

        $this->assertDatabaseMissing('campaign_communications', [
            'campaign_id' => $campaignA->id,
            'content_item_id' => $contentB->id,
        ]);
    }
}
