<?php

namespace Tests\Feature\Design;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Design\Actions\CreateDesign;
use App\Design\Rendering\DesignRenderingContextFactory;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Enums\ContentType;
use App\Enums\DesignOutputFormat;
use App\Enums\DesignOutputStatus;
use App\Enums\DesignPurpose;
use App\Enums\DesignState;
use App\Models\Campaign;
use App\Models\CampaignCommunication;
use App\Models\Church;
use App\Models\ChurchBrandProfile;
use App\Models\ContentItem;
use App\Models\Design;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class DesignFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Grace Centre', 'slug' => 'grace-centre']);
        $this->user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->user);
    }

    public function test_design_creation_snapshots_sources_brand_media_and_requested_formats(): void
    {
        $logo = $this->asset('logo.png', 1200, 1200);
        $background = $this->asset('background.jpg', 1800, 1800);
        ChurchBrandProfile::create([
            'primary_logo_media_id' => $logo->id,
            'primary_color' => '#123456',
            'accent_color' => '#F0C040',
            'heading_font' => 'geist',
            'body_font' => 'inter',
        ]);
        $content = ContentItem::create([
            'title' => 'Sunday copy',
            'content_type' => ContentType::ANNOUNCEMENT,
            'body' => 'Join us this Sunday.',
        ]);
        $campaign = app(CampaignManager::class)->create(['title' => 'Sunday Invitation']);
        $communication = app(CampaignCommunicationManager::class)->add($campaign, [
            'title' => 'Sunday artwork',
            'channel' => CommunicationChannel::INSTAGRAM,
        ]);
        app(CampaignCommunicationManager::class)->linkContentItem($communication, $content);

        $design = $this->create([
            'mediaBySlot' => ['background' => $background->id],
            'formats' => [DesignOutputFormat::STORY, DesignOutputFormat::SQUARE],
            'contentItemId' => $content->id,
            'campaignId' => $campaign->id,
            'campaignCommunicationId' => $communication->id,
        ]);

        $this->assertSame($this->church->id, $design->church_id);
        $this->assertSame($this->user->id, $design->created_by);
        $this->assertSame(DesignState::DRAFT, $design->state);
        $this->assertSame($content->id, $design->content_item_id);
        $this->assertSame($campaign->id, $design->campaign_id);
        $this->assertSame($communication->id, $design->campaign_communication_id);
        $this->assertSame($logo->id, $design->primary_logo_media_id);
        $this->assertSame('#123456', $design->brand_snapshot['background']);
        $this->assertSame('geist', $design->brand_snapshot['heading_font']);
        $this->assertSame(['square', 'story'], $design->outputs->pluck('format')->map->value->all());
        $this->assertSame($background->id, $design->mediaSelections->sole()->media_asset_id);
    }

    public function test_rendering_context_is_deterministic_and_contains_no_eloquent_models(): void
    {
        $background = $this->asset('background.jpg', 1800, 1800);
        $design = $this->create(['mediaBySlot' => ['background' => $background->id]]);
        $factory = app(DesignRenderingContextFactory::class);

        $first = $factory->forDesign($design);
        $second = $factory->forDesign($design->fresh());

        $this->assertEquals($first, $second);
        $this->assertSame($this->church->name, $first->churchName);
        $this->assertSame($background->path, $first->media['background']->path);
        $this->assertSame(['date', 'time', 'title'], array_keys($first->inputs));
    }

    public function test_foreign_media_content_campaign_and_communication_are_rejected(): void
    {
        [$foreignContent, $foreignCampaign, $foreignCommunication, $foreignMedia] = $this->foreignSources();

        foreach ([
            ['mediaBySlot' => ['background' => $foreignMedia->id]],
            ['contentItemId' => $foreignContent->id],
            ['campaignId' => $foreignCampaign->id],
            ['campaignId' => $foreignCampaign->id, 'campaignCommunicationId' => $foreignCommunication->id],
        ] as $override) {
            try {
                $this->create($override);
                $this->fail('A foreign Design source was accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('designs', 0);
            }
        }
    }

    public function test_unsupported_purpose_format_variant_and_unknown_slots_fail_safely(): void
    {
        foreach ([
            ['purpose' => DesignPurpose::CAMPAIGN],
            ['variant' => 'tenant-css'],
            ['inputs' => $this->inputs() + ['html' => '<script>alert(1)</script>']],
        ] as $override) {
            try {
                $this->create($override);
                $this->fail('An unsupported template contract was accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('designs', 0);
            }
        }
    }

    public function test_output_failure_is_per_format_and_design_approval_requires_complete_success(): void
    {
        $design = $this->create(['formats' => [DesignOutputFormat::SQUARE, DesignOutputFormat::STORY]]);
        $square = $design->outputs->firstWhere('format', DesignOutputFormat::SQUARE);
        $story = $design->outputs->firstWhere('format', DesignOutputFormat::STORY);
        $squareAsset = $this->asset('generated-square.png', 1080, 1080);

        $square->markRendered($squareAsset);
        $story->markFailed('renderer_timeout');

        $this->assertSame(DesignOutputStatus::RENDERED, $square->fresh()->status);
        $this->assertSame(DesignOutputStatus::FAILED, $story->fresh()->status);

        $this->expectException(LogicException::class);
        $design->approve($this->user);
    }

    public function test_output_dimensions_and_same_church_asset_are_enforced(): void
    {
        $output = $this->create()->outputs->sole();
        $wrongDimensions = $this->asset('wrong.png', 1200, 1200);

        $this->expectException(LogicException::class);
        $output->markRendered($wrongDimensions);
    }

    public function test_communications_can_manage_designs_but_other_responsibilities_and_primary_only_cannot(): void
    {
        $design = $this->create();
        $this->assertTrue(Gate::allows('view', $design));
        $this->assertTrue(Gate::allows('update', $design));
        $this->assertTrue(Gate::allows('approve', $design));

        foreach ([ChurchRole::ADMINISTRATOR, ChurchRole::CARE, null] as $role) {
            $user = User::factory()->forChurch($this->church, $role ? [$role] : [], primary: $role === null)->create();
            $this->actingAs($user);
            app(TenantContext::class)->forgetResolved();

            $this->assertFalse(Gate::allows('viewAny', Design::class));
            $this->assertFalse(Gate::allows('create', Design::class));
        }
    }

    /** @param array<string, mixed> $override */
    private function create(array $override = []): Design
    {
        $arguments = array_merge([
            'templateKey' => 'sunday-modern-reference',
            'templateVersion' => 1,
            'purpose' => DesignPurpose::SERVICE,
            'inputs' => $this->inputs(),
            'formats' => [DesignOutputFormat::SQUARE],
        ], $override);

        return app(CreateDesign::class)->handle(...$arguments);
    }

    /** @return array<string, string> */
    private function inputs(): array
    {
        return ['title' => 'Sunday Worship', 'date' => '2026-08-23', 'time' => '09:30'];
    }

    private function asset(string $filename, int $width, int $height): MediaAsset
    {
        return MediaAsset::create([
            'disk' => 'public',
            'path' => "tenants/{$this->church->id}/media/".uniqid().'/'.$filename,
            'original_filename' => $filename,
            'mime_type' => str_ends_with($filename, '.jpg') ? 'image/jpeg' : 'image/png',
            'size' => 1000,
            'width' => $width,
            'height' => $height,
            'alt_text' => 'Church communication image',
        ]);
    }

    /** @return array{ContentItem, Campaign, CampaignCommunication, MediaAsset} */
    private function foreignSources(): array
    {
        $foreignChurch = Church::create(['name' => 'Foreign Church', 'slug' => 'foreign-design-church']);
        $foreignUser = User::factory()->forChurch($foreignChurch, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($foreignUser);
        app(TenantContext::class)->forgetResolved();
        $content = ContentItem::create(['title' => 'Foreign', 'content_type' => ContentType::GENERAL, 'body' => 'Foreign body']);
        $campaign = app(CampaignManager::class)->create(['title' => 'Foreign campaign']);
        $communication = app(CampaignCommunicationManager::class)->add($campaign, [
            'title' => 'Foreign communication',
            'channel' => CommunicationChannel::GENERAL,
        ]);
        $media = $this->asset('foreign.jpg', 1800, 1800);

        $this->actingAs($this->user);
        app(TenantContext::class)->forgetResolved();

        return [$content, $campaign, $communication, $media];
    }
}
