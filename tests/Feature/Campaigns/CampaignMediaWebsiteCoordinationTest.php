<?php

namespace Tests\Feature\Campaigns;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Campaigns\CampaignMediaManager;
use App\Enums\Capability;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Filament\Pages\CampaignWorkspace;
use App\Models\CampaignMedia;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ContentItem;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class CampaignMediaWebsiteCoordinationTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->church = Church::create(['name' => 'Campaign Integration Church', 'slug' => 'campaign-integration']);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
    }

    private function asset(Church $church, string $name = 'easter-hero.png'): MediaAsset
    {
        return MediaAsset::create([
            'church_id' => $church->id,
            'disk' => 'public',
            'path' => "tenants/{$church->id}/media/fixture/original.png",
            'original_filename' => $name,
            'mime_type' => 'image/png',
            'size' => 128,
            'width' => 1200,
            'height' => 675,
            'alt_text' => 'Easter gathering',
        ]);
    }

    public function test_same_church_media_attaches_once_and_detaching_preserves_the_asset(): void
    {
        $campaign = app(CampaignManager::class)->create(['title' => 'Easter']);
        $asset = $this->asset($this->church);
        $manager = app(CampaignMediaManager::class);

        $first = $manager->attach($campaign, $asset, 'Hero artwork');
        $second = $manager->attach($campaign, $asset, 'Duplicate label');

        $this->assertTrue($first->is($second));
        $this->assertSame(1, CampaignMedia::query()->count());
        $this->assertSame('Hero artwork', $first->label);

        $manager->detach($first);

        $this->assertDatabaseMissing('campaign_media', ['id' => $first->id]);
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'deleted_at' => null]);
    }

    public function test_foreign_or_deleted_media_fails_closed_and_deleted_preview_is_graceful(): void
    {
        $campaign = app(CampaignManager::class)->create(['title' => 'Outreach']);
        $other = Church::create(['name' => 'Other Church', 'slug' => 'other-media-church']);
        $foreign = new MediaAsset([
            'disk' => 'public', 'path' => 'foreign/original.png', 'original_filename' => 'foreign.png',
            'mime_type' => 'image/png', 'size' => 10,
        ]);
        $foreign->forceFill(['church_id' => $other->id])->save();

        try {
            app(CampaignMediaManager::class)->attach($campaign, $foreign);
            $this->fail('Foreign media should be rejected.');
        } catch (AuthorizationException) {
            $this->assertSame(0, CampaignMedia::query()->count());
        }

        $asset = $this->asset($this->church);
        $association = app(CampaignMediaManager::class)->attach($campaign, $asset);
        $asset->delete();

        Livewire::test(CampaignWorkspace::class, ['campaign' => $campaign->id])
            ->assertSuccessful()
            ->assertSee('Asset preview unavailable')
            ->assertSee('easter-hero.png');

        $this->assertNotNull($association->fresh()->mediaAsset);
    }

    public function test_draft_campaign_deletion_removes_association_but_preserves_media(): void
    {
        $campaign = app(CampaignManager::class)->create(['title' => 'Disposable']);
        $asset = $this->asset($this->church);
        app(CampaignMediaManager::class)->attach($campaign, $asset);

        app(CampaignManager::class)->delete($campaign);

        $this->assertSame(0, CampaignMedia::query()->count());
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'deleted_at' => null]);
    }

    public function test_media_association_requires_campaign_and_media_capabilities(): void
    {
        $campaign = app(CampaignManager::class)->create(['title' => 'Capability']);
        $asset = $this->asset($this->church);
        $membership = Mockery::mock(ChurchMembership::class)->makePartial();
        $membership->church_id = $this->church->id;
        $membership->shouldReceive('hasCapability')->andReturnUsing(
            fn (Capability $capability): bool => $capability === Capability::CampaignsManage,
        );
        $tenant = Mockery::mock(TenantContext::class);
        $tenant->shouldReceive('currentMembership')->andReturn($membership);
        $this->app->instance(TenantContext::class, $tenant);

        $this->expectException(AuthorizationException::class);
        app(CampaignMediaManager::class)->attach($campaign, $asset);
    }

    public function test_website_coordination_is_truthful_and_does_not_mutate_website_state(): void
    {
        $campaign = app(CampaignManager::class)->create(['title' => 'Christmas']);
        $communication = app(CampaignCommunicationManager::class)->add($campaign, [
            'title' => 'Christmas home hero',
            'channel' => CommunicationChannel::WEBSITE,
        ]);
        $content = ContentItem::create([
            'title' => 'Approved Website copy',
            'content_type' => ContentType::WEBSITE_COPY,
            'body' => 'Approved copy.',
        ]);
        $content->forceFill(['status' => ContentStatus::APPROVED])->save();
        app(CampaignCommunicationManager::class)->linkContentItem($communication, $content);

        Livewire::test(CampaignWorkspace::class, ['campaign' => $campaign->id])
            ->assertSuccessful()
            ->assertSee('Content ready')
            ->assertSee('Website action required')
            ->assertSee('Content readiness does not mean the Website has been updated')
            ->assertSee('Open Website');

        $this->assertDatabaseCount('website_publications', 0);
    }
}
