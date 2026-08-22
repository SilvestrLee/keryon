<?php

namespace Tests\Feature\Campaigns;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Enums\CampaignStatus;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignDomainTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $church = Church::create(['name' => 'Campaign Domain Church', 'slug' => 'campaign-domain-church']);
        $this->user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->user);
    }

    public function test_creation_derives_tenant_and_actor_and_ignores_caller_actor_ids(): void
    {
        $other = User::factory()->create();

        $campaign = app(CampaignManager::class)->create([
            'title' => 'Easter 2027',
            'purpose' => 'Invite the community to Easter services.',
            'created_by' => $other->id,
            'updated_by' => $other->id,
        ]);

        $this->assertSame($this->user->memberships()->first()->church_id, $campaign->church_id);
        $this->assertSame($this->user->id, $campaign->created_by);
        $this->assertSame($this->user->id, $campaign->updated_by);
        $this->assertSame(CampaignStatus::DRAFT, $campaign->status);

        $second = User::factory()->forChurch($campaign->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($second);
        app(TenantContext::class)->forgetResolved();

        app(CampaignManager::class)->update($campaign, ['title' => 'Easter Weekend 2027', 'updated_by' => $other->id]);

        $this->assertSame($second->id, $campaign->fresh()->updated_by);
        $this->assertSame($this->user->id, $campaign->fresh()->created_by);
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $this->expectException(ValidationException::class);

        app(CampaignManager::class)->create([
            'title' => 'Invalid dates',
            'starts_on' => '2027-04-04',
            'ends_on' => '2027-03-21',
        ]);
    }

    public function test_dates_do_not_transition_status(): void
    {
        $campaign = app(CampaignManager::class)->create([
            'title' => 'Past Campaign',
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->subWeek()->toDateString(),
        ]);

        $this->assertSame(CampaignStatus::DRAFT, $campaign->fresh()->status);
    }

    public function test_every_approved_transition_and_revision_path(): void
    {
        $campaign = app(CampaignManager::class)->create(['title' => 'Lifecycle']);
        $manager = app(CampaignManager::class);

        $manager->transition($campaign, CampaignStatus::PLANNED);
        $manager->transition($campaign, CampaignStatus::DRAFT);
        $manager->transition($campaign, CampaignStatus::PLANNED);
        $manager->transition($campaign, CampaignStatus::ACTIVE);

        $this->expectException(LogicException::class);
        $manager->transition($campaign, CampaignStatus::PLANNED);
    }

    public function test_active_to_planned_requires_confirmation_then_completion_and_archive_succeed(): void
    {
        $campaign = app(CampaignManager::class)->create(['title' => 'Confirmed lifecycle']);
        $manager = app(CampaignManager::class);
        $manager->transition($campaign, CampaignStatus::PLANNED);
        $manager->transition($campaign, CampaignStatus::ACTIVE);
        $manager->transition($campaign, CampaignStatus::PLANNED, confirmed: true);
        $manager->transition($campaign, CampaignStatus::ACTIVE);
        $manager->transition($campaign, CampaignStatus::COMPLETED);
        $manager->transition($campaign, CampaignStatus::ARCHIVED);

        $this->assertSame(CampaignStatus::ARCHIVED, $campaign->fresh()->status);
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_forbidden_transition_is_rejected(CampaignStatus $from, CampaignStatus $to): void
    {
        $campaign = app(CampaignManager::class)->create(['title' => 'Forbidden transition']);
        $campaign->forceFill(['status' => $from])->save();

        $this->expectException(LogicException::class);
        app(CampaignManager::class)->transition($campaign, $to);
    }

    public static function forbiddenTransitions(): array
    {
        return [
            'draft to completed' => [CampaignStatus::DRAFT, CampaignStatus::COMPLETED],
            'draft to archived' => [CampaignStatus::DRAFT, CampaignStatus::ARCHIVED],
            'completed to active' => [CampaignStatus::COMPLETED, CampaignStatus::ACTIVE],
            'archived to active' => [CampaignStatus::ARCHIVED, CampaignStatus::ACTIVE],
        ];
    }

    public function test_draft_deletion_soft_deletes_owned_plan_entries_but_not_content(): void
    {
        $campaign = app(CampaignManager::class)->create(['title' => 'Disposable draft']);
        $communication = app(CampaignCommunicationManager::class)->add($campaign, [
            'title' => 'Announcement',
            'channel' => CommunicationChannel::GENERAL,
        ]);
        $content = ContentItem::create(['title' => 'Canonical copy', 'content_type' => 'campaign_copy', 'body' => 'Body']);
        app(CampaignCommunicationManager::class)->linkContentItem($communication, $content);

        app(CampaignManager::class)->delete($campaign);

        $this->assertSoftDeleted('campaigns', ['id' => $campaign->id]);
        $this->assertSoftDeleted('campaign_communications', ['id' => $communication->id]);
        $this->assertDatabaseHas('content_items', ['id' => $content->id, 'deleted_at' => null]);
    }

    public function test_non_draft_campaign_cannot_be_deleted(): void
    {
        $campaign = app(CampaignManager::class)->create(['title' => 'Historical campaign']);
        app(CampaignManager::class)->transition($campaign, CampaignStatus::PLANNED);

        $this->expectException(LogicException::class);
        app(CampaignManager::class)->delete($campaign);
    }
}
