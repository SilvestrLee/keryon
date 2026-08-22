<?php

namespace Tests\Feature\FaithFlow;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Enums\CampaignStatus;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Enums\ContentOrigin;
use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\Enums\FaithFlowRunStatus;
use App\FaithFlow\Actions\ApproveFaithFlowOutput;
use App\Filament\Pages\FaithFlow;
use App\Models\CampaignCommunication;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Mockery;
use Tests\TestCase;

class FaithFlowCampaignContextTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private CampaignCommunication $communication;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->church = Church::create(['name' => 'FaithFlow Campaign Church', 'slug' => 'faithflow-campaign-church']);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        $campaign = app(CampaignManager::class)->create(['title' => 'Outreach Campaign']);
        $this->communication = app(CampaignCommunicationManager::class)->add($campaign, [
            'title' => 'Community invitation',
            'purpose' => 'Invite neighbours to the outreach.',
            'channel' => CommunicationChannel::FACEBOOK,
        ]);
    }

    private function runWithContext(): FaithFlowRun
    {
        return FaithFlowRun::factory()->forChurch($this->church)->create([
            'campaign_communication_id' => $this->communication->id,
            'status' => FaithFlowRunStatus::ANALYZED,
            'canonical_analysis' => [
                'source_summary' => 'A message about serving neighbours.',
                'ministry_context' => 'Community outreach',
            ],
        ]);
    }

    public function test_new_faithflow_run_retains_explicit_context_without_mutating_source_or_prompt_inputs(): void
    {
        $source = str_repeat('This visible source remains the human supplied generation input. ', 3);

        Livewire::test(FaithFlow::class, ['campaignCommunicationId' => $this->communication->id])
            ->assertSee('Creating for Campaign')
            ->assertSee('Community invitation')
            ->set('sourceText', $source)
            ->call('createSource');

        $run = FaithFlowRun::query()->sole();
        $this->assertSame($this->communication->id, $run->campaign_communication_id);
        $this->assertSame($source, $run->source_text);
        $this->assertNull($run->canonical_analysis);
        $this->assertNull($run->prompt_version);
    }

    public function test_approved_output_atomically_creates_canonical_content_and_links_the_plan(): void
    {
        $run = $this->runWithContext();
        $output = FaithFlowOutput::factory()->forRun($run)->generated()->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
            'content' => 'Reviewed FaithFlow content.',
        ]);

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->call('approveOutput', $output->id);

        $content = ContentItem::query()->sole();
        $this->assertSame(ContentOrigin::FAITHFLOW, $content->origin);
        $this->assertSame($content->id, $output->fresh()->content_item_id);
        $this->assertSame($content->id, $this->communication->fresh()->content_item_id);
        $this->assertSame(auth()->id(), $output->fresh()->approved_by);
    }

    public function test_reapproval_converges_on_one_content_item_and_one_campaign_link(): void
    {
        $run = $this->runWithContext();
        $output = FaithFlowOutput::factory()->forRun($run)->generated()->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
        ]);
        $action = app(ApproveFaithFlowOutput::class);

        $action->handle($output);
        $action->handle($output->fresh());

        $this->assertSame(1, ContentItem::query()->count());
        $this->assertSame($output->fresh()->content_item_id, $this->communication->fresh()->content_item_id);
    }

    public function test_link_failure_rolls_back_approval_content_creation_and_campaign_link(): void
    {
        $run = $this->runWithContext();
        $output = FaithFlowOutput::factory()->forRun($run)->generated()->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
        ]);

        $manager = Mockery::mock(CampaignCommunicationManager::class);
        $manager->shouldReceive('linkContentItem')->once()->andThrow(new LogicException('Controlled link failure.'));
        $this->app->instance(CampaignCommunicationManager::class, $manager);

        try {
            app(ApproveFaithFlowOutput::class)->handle($output);
            $this->fail('The controlled failure should escape the domain action.');
        } catch (LogicException $exception) {
            $this->assertSame('Controlled link failure.', $exception->getMessage());
        }

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $output->fresh()->status);
        $this->assertNull($output->fresh()->content_item_id);
        $this->assertNull($this->communication->fresh()->content_item_id);
        $this->assertSame(0, ContentItem::query()->count());
    }

    public function test_cross_church_context_is_rejected_and_ordinary_runs_remain_unaffected(): void
    {
        $churchB = Church::create(['name' => 'Foreign FaithFlow Church', 'slug' => 'foreign-faithflow-church']);
        $this->actingAs(User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        $this->get(FaithFlow::getUrl([
            'campaign_communication' => $this->communication->id,
        ]))->assertForbidden();

        $ordinary = FaithFlowRun::factory()->forChurch($churchB)->create();
        Livewire::test(FaithFlow::class, ['run' => $ordinary->id])
            ->assertSuccessful()
            ->assertDontSee('Creating for Campaign');
    }

    public function test_archived_campaign_blocks_new_context_but_does_not_hide_an_existing_faithflow_run(): void
    {
        $run = $this->runWithContext();
        $campaign = $this->communication->campaign;
        $manager = app(CampaignManager::class);
        $manager->transition($campaign, CampaignStatus::PLANNED);
        $manager->transition($campaign, CampaignStatus::ACTIVE);
        $manager->transition($campaign, CampaignStatus::COMPLETED);
        $manager->transition($campaign, CampaignStatus::ARCHIVED);

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->assertSuccessful()
            ->assertSee('Creating for Campaign')
            ->assertSee('Back to Campaign');
    }

    public function test_existing_run_may_finish_after_campaign_completion_but_new_work_cannot_start(): void
    {
        $run = $this->runWithContext();
        $output = FaithFlowOutput::factory()->forRun($run)->generated()->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
        ]);
        $campaign = $this->communication->campaign;
        $manager = app(CampaignManager::class);
        $manager->transition($campaign, CampaignStatus::PLANNED);
        $manager->transition($campaign, CampaignStatus::ACTIVE);
        $manager->transition($campaign, CampaignStatus::COMPLETED);

        app(ApproveFaithFlowOutput::class)->handle($output);

        $this->assertNotNull($this->communication->fresh()->content_item_id);

        $newCommunication = app(CampaignCommunicationManager::class)->add($campaign, [
            'title' => 'Late new work',
            'channel' => CommunicationChannel::GENERAL,
        ]);
        $this->get(FaithFlow::getUrl(['campaign_communication' => $newCommunication->id]))->assertForbidden();
    }

    public function test_archived_or_cancelled_context_cannot_finish_faithflow_handoff(): void
    {
        $run = $this->runWithContext();
        $output = FaithFlowOutput::factory()->forRun($run)->generated()->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
        ]);
        app(CampaignCommunicationManager::class)->cancel($this->communication);

        $this->expectException(LogicException::class);
        app(ApproveFaithFlowOutput::class)->handle($output);
    }

    public function test_archived_campaign_cannot_finish_an_existing_faithflow_handoff(): void
    {
        $run = $this->runWithContext();
        $output = FaithFlowOutput::factory()->forRun($run)->generated()->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
        ]);
        $campaign = $this->communication->campaign;
        $manager = app(CampaignManager::class);
        $manager->transition($campaign, CampaignStatus::PLANNED);
        $manager->transition($campaign, CampaignStatus::ACTIVE);
        $manager->transition($campaign, CampaignStatus::COMPLETED);
        $manager->transition($campaign, CampaignStatus::ARCHIVED);

        $this->expectException(LogicException::class);
        app(ApproveFaithFlowOutput::class)->handle($output);
    }
}
