<?php

namespace Tests\Feature\Campaigns;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Enums\ContentStatus;
use App\Models\CampaignCommunication;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignCommunicationTest extends TestCase
{
    use RefreshDatabase;

    private CampaignCommunicationManager $communications;

    protected function setUp(): void
    {
        parent::setUp();

        $church = Church::create(['name' => 'Communication Plan Church', 'slug' => 'communication-plan-church']);
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        $this->communications = app(CampaignCommunicationManager::class);
    }

    private function campaign(string $title = 'Easter')
    {
        return app(CampaignManager::class)->create(['title' => $title]);
    }

    private function communication($campaign, string $title = 'Announcement'): CampaignCommunication
    {
        return $this->communications->add($campaign, [
            'title' => $title,
            'purpose' => 'Tell the church why this communication exists.',
            'channel' => CommunicationChannel::GENERAL,
        ]);
    }

    private function content(ContentStatus $status): ContentItem
    {
        $item = ContentItem::create([
            'title' => 'Campaign copy '.uniqid(),
            'content_type' => 'campaign_copy',
            'body' => 'Canonical Content Studio body.',
        ]);
        $item->forceFill(['status' => $status])->save();

        return $item;
    }

    public function test_plan_entry_can_exist_without_content_and_target_is_only_planning_data(): void
    {
        $entry = $this->communications->add($this->campaign(), [
            'title' => 'Easter morning post',
            'purpose' => 'Welcome visitors on Easter morning.',
            'channel' => CommunicationChannel::INSTAGRAM,
            'target_at' => '2027-04-04 08:00:00',
        ]);

        $this->assertNull($entry->content_item_id);
        $this->assertSame(CampaignCommunication::READINESS_NOT_STARTED, $entry->readiness());
        $this->assertSame('instagram', $entry->channel->value);
        $this->assertSame('2027-04-04 08:00:00', $entry->target_at->format('Y-m-d H:i:s'));
    }

    #[DataProvider('readinessCases')]
    public function test_readiness_is_derived_from_canonical_content_status(ContentStatus $status, string $expected): void
    {
        $entry = $this->communication($this->campaign());
        $content = $this->content($status);
        $this->communications->linkContentItem($entry, $content);

        $this->assertSame($expected, $entry->fresh()->readiness());
        $this->assertSame($status, $content->fresh()->status);
    }

    public static function readinessCases(): array
    {
        return [
            'draft' => [ContentStatus::DRAFT, CampaignCommunication::READINESS_IN_PREPARATION],
            'needs changes' => [ContentStatus::REJECTED, CampaignCommunication::READINESS_IN_PREPARATION],
            'review' => [ContentStatus::REVIEW, CampaignCommunication::READINESS_AWAITING_APPROVAL],
            'approved' => [ContentStatus::APPROVED, CampaignCommunication::READINESS_PREPARED],
        ];
    }

    public function test_cancelled_is_explicit_excluded_state_and_can_be_restored(): void
    {
        $entry = $this->communication($this->campaign());
        $content = $this->content(ContentStatus::APPROVED);
        $this->communications->linkContentItem($entry, $content);

        $this->communications->cancel($entry);
        $this->assertSame(CampaignCommunication::READINESS_CANCELLED, $entry->fresh()->readiness());

        $this->communications->restore($entry);
        $this->assertSame(CampaignCommunication::READINESS_PREPARED, $entry->fresh()->readiness());
    }

    public function test_soft_deleted_linked_content_is_outstanding(): void
    {
        $entry = $this->communication($this->campaign());
        $content = $this->content(ContentStatus::APPROVED);
        $this->communications->linkContentItem($entry, $content);
        $content->delete();

        $this->assertSame(CampaignCommunication::READINESS_OUTSTANDING, $entry->fresh()->readiness());
    }

    public function test_duplicate_same_campaign_content_link_is_prevented(): void
    {
        $campaign = $this->campaign();
        $first = $this->communication($campaign, 'First');
        $second = $this->communication($campaign, 'Second');
        $content = $this->content(ContentStatus::DRAFT);
        $this->communications->linkContentItem($first, $content);

        $this->expectException(LogicException::class);
        $this->communications->linkContentItem($second, $content);
    }

    public function test_same_content_can_satisfy_communications_across_campaigns(): void
    {
        $content = $this->content(ContentStatus::APPROVED);
        $first = $this->communication($this->campaign('Easter'), 'Easter announcement');
        $second = $this->communication($this->campaign('Anniversary'), 'Anniversary recap');

        $this->communications->linkContentItem($first, $content);
        $this->communications->linkContentItem($second, $content);

        $this->assertCount(2, $content->campaignCommunications);
    }

    public function test_unlink_cancel_and_delete_never_mutate_or_delete_content(): void
    {
        $entry = $this->communication($this->campaign());
        $content = $this->content(ContentStatus::APPROVED);
        $this->communications->linkContentItem($entry, $content);
        $this->communications->cancel($entry);
        $this->communications->unlinkContentItem($entry);
        $this->communications->delete($entry);

        $this->assertDatabaseHas('content_items', [
            'id' => $content->id,
            'status' => ContentStatus::APPROVED->value,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted('campaign_communications', ['id' => $entry->id]);
    }
}
