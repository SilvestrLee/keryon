<?php

namespace Tests\Feature\Campaigns;

use App\Campaigns\CampaignCommunicationContext;
use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Enums\CampaignStatus;
use App\Enums\Capability;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Filament\Pages\CampaignWorkspace;
use App\Filament\Resources\ContentItemResource;
use App\Filament\Resources\ContentItemResource\Pages\ListContentItems;
use App\Models\CampaignCommunication;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class CampaignContentHandoffTest extends TestCase
{
    use RefreshDatabase;

    private CampaignManager $campaigns;

    private CampaignCommunicationManager $communications;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $church = Church::create(['name' => 'Context Church', 'slug' => 'context-church']);
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        $this->campaigns = app(CampaignManager::class);
        $this->communications = app(CampaignCommunicationManager::class);
    }

    private function communication(CommunicationChannel $channel = CommunicationChannel::GENERAL): CampaignCommunication
    {
        $campaign = $this->campaigns->create(['title' => 'Easter 2027']);

        return $this->communications->add($campaign, [
            'title' => 'Easter announcement',
            'purpose' => 'Invite the community clearly.',
            'channel' => $channel,
        ]);
    }

    public function test_content_studio_context_prefills_editable_fields_and_atomically_links_the_created_item(): void
    {
        $communication = $this->communication(CommunicationChannel::INSTAGRAM);

        Livewire::test(ListContentItems::class, ['campaignCommunicationId' => $communication->id])
            ->mountAction('create')
            ->assertActionDataSet([
                'title' => 'Easter announcement',
                'content_type' => ContentType::SOCIAL_CAPTION->value,
            ])
            ->setActionData([
                'title' => 'Editable campaign title',
                'content_type' => ContentType::ANNOUNCEMENT->value,
                'body' => 'Canonical content written by a human.',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertRedirect(CampaignWorkspace::getUrl(['campaign' => $communication->campaign_id]));

        $content = ContentItem::query()->sole();
        $this->assertSame('Editable campaign title', $content->title);
        $this->assertSame(ContentType::ANNOUNCEMENT, $content->content_type);
        $this->assertSame(ContentStatus::DRAFT, $content->status);
        $this->assertSame($content->id, $communication->fresh()->content_item_id);
    }

    public function test_ambiguous_general_channel_does_not_invent_a_content_type(): void
    {
        $communication = $this->communication();

        Livewire::test(ListContentItems::class, ['campaignCommunicationId' => $communication->id])
            ->mountAction('create')
            ->assertActionDataSet(fn (array $state): bool => empty($state['content_type']));
    }

    public function test_cancelled_and_archived_contexts_cannot_start_content_creation(): void
    {
        $cancelled = $this->communication();
        $this->communications->cancel($cancelled);

        Livewire::test(ListContentItems::class, ['campaignCommunicationId' => $cancelled->id])
            ->assertForbidden();

        $archived = $this->communication();
        $campaign = $archived->campaign;
        $this->campaigns->transition($campaign, CampaignStatus::PLANNED);
        $this->campaigns->transition($campaign, CampaignStatus::ACTIVE);
        $this->campaigns->transition($campaign, CampaignStatus::COMPLETED);
        $this->campaigns->transition($campaign, CampaignStatus::ARCHIVED);

        Livewire::test(ListContentItems::class, ['campaignCommunicationId' => $archived->id])
            ->assertForbidden();
    }

    public function test_foreign_context_fails_closed_and_normal_content_creation_remains_unchanged(): void
    {
        $foreign = $this->communication();
        $churchB = Church::create(['name' => 'Other Context Church', 'slug' => 'other-context-church']);
        $this->actingAs(User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        $this->get(ContentItemResource::getUrl('index', [
            'campaign_communication' => $foreign->id,
        ]))->assertForbidden();

        Livewire::test(ListContentItems::class)
            ->callAction('create', data: [
                'title' => 'Ordinary Content Studio draft',
                'content_type' => ContentType::GENERAL->value,
                'body' => 'Normal creation has no Campaign context.',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('content_items', [
            'church_id' => $churchB->id,
            'title' => 'Ordinary Content Studio draft',
        ]);
    }

    public function test_workspace_exposes_contextual_actions_only_for_unlinked_actionable_entries(): void
    {
        $communication = $this->communication();

        Livewire::test(CampaignWorkspace::class, ['campaign' => $communication->campaign_id])
            ->assertSee('Create content')
            ->assertSee('Create with FaithFlow')
            ->assertSee('Link existing content');

        $content = ContentItem::create([
            'title' => 'Existing linked content',
            'content_type' => ContentType::GENERAL,
            'body' => 'Existing body.',
        ]);
        $this->communications->linkContentItem($communication, $content);

        Livewire::test(CampaignWorkspace::class, ['campaign' => $communication->campaign_id])
            ->assertSee('View content')
            ->assertDontSee('Create with FaithFlow');
    }

    public function test_cross_domain_context_requires_both_campaign_and_adjacent_capabilities(): void
    {
        $communication = $this->communication();
        $membership = Mockery::mock(ChurchMembership::class)->makePartial();
        $membership->church_id = $communication->church_id;
        $membership->shouldReceive('hasCapability')->andReturnUsing(
            fn (Capability $capability): bool => $capability === Capability::CampaignsManage,
        );
        $tenant = Mockery::mock(TenantContext::class);
        $tenant->shouldReceive('currentMembership')->andReturn($membership);
        $tenant->shouldReceive('currentChurchId')->andReturn($communication->church_id);
        $this->app->instance(TenantContext::class, $tenant);

        $this->expectException(AuthorizationException::class);
        app(CampaignCommunicationContext::class)
            ->forContentCreation($communication->id);
    }

    public function test_content_and_faithflow_capabilities_without_campaign_management_cannot_mutate_campaign_context(): void
    {
        $communication = $this->communication();
        $membership = Mockery::mock(ChurchMembership::class)->makePartial();
        $membership->church_id = $communication->church_id;
        $membership->shouldReceive('hasCapability')->andReturnUsing(
            fn (Capability $capability): bool => in_array($capability, [Capability::ContentManage, Capability::FaithflowUse], true),
        );
        $tenant = Mockery::mock(TenantContext::class);
        $tenant->shouldReceive('currentMembership')->andReturn($membership);
        $tenant->shouldReceive('currentChurchId')->andReturn($communication->church_id);
        $this->app->instance(TenantContext::class, $tenant);

        $this->expectException(AuthorizationException::class);
        app(CampaignCommunicationContext::class)
            ->forFaithFlow($communication->id);
    }
}
