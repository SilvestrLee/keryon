<?php

namespace Tests\Feature\Media;

use App\Campaigns\CampaignManager;
use App\Campaigns\CampaignMediaManager;
use App\Enums\AssetRightsStatus;
use App\Enums\ChurchRole;
use App\Enums\DesignPurpose;
use App\Filament\Pages\MediaLibraryDetail;
use App\Media\ChurchMediaLibraryQuery;
use App\Media\IngestMediaAsset;
use App\Models\Church;
use App\Models\ChurchBrandProfile;
use App\Models\Design;
use App\Models\DesignMedia;
use App\Models\DesignOutput;
use App\Models\MediaAsset;
use App\Models\User;
use App\Trust\Rights\RecordMediaAssetRights;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-MEDIA-V1-001B §59/§60 — Media detail (metadata, provenance, rights,
 * attribution, alt-text edit) and usage (Brand/Campaign/Design/Website)
 * test matrices.
 */
class MediaLibraryDetailAndUsageTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('media-private');
        $this->church = Church::factory()->create();
        $this->user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->user);
    }

    protected function fakePngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    protected function ingest(string $filename = 'hero.png', ?string $altText = null): MediaAsset
    {
        $staged = "tenants/{$this->church->id}/media/.staging/".uniqid().'.tmp';
        Storage::disk('media-private')->put($staged, $this->fakePngBytes());

        return app(IngestMediaAsset::class)->handle($staged, $filename, $altText);
    }

    // ---------------------------------------------------------------
    // §59 — detail
    // ---------------------------------------------------------------

    public function test_metadata_is_visible_on_the_detail_page(): void
    {
        $asset = $this->ingest('sanctuary.png');

        Livewire::test(MediaLibraryDetail::class, ['asset' => $asset->uuid])
            ->assertSee('sanctuary.png')
            ->assertSee('PNG image')
            ->assertSee('1×1');
    }

    public function test_human_provenance_is_visible(): void
    {
        $asset = $this->ingest('provenance.png');

        Livewire::test(MediaLibraryDetail::class, ['asset' => $asset->uuid])
            ->assertSee('Uploaded by your Church');
    }

    public function test_rights_status_is_visible(): void
    {
        $asset = $this->ingest('rights.png');
        app(RecordMediaAssetRights::class)->restrict($asset, AssetRightsStatus::Restricted, 'Under review.');

        Livewire::test(MediaLibraryDetail::class, ['asset' => $asset->uuid])
            ->assertSee('Restricted');
    }

    public function test_attribution_is_visible_when_required(): void
    {
        $asset = $this->ingest('attribution.png');
        app(RecordMediaAssetRights::class)->organizationShared($asset, true, 'Photo courtesy of Diocese Network.');

        Livewire::test(MediaLibraryDetail::class, ['asset' => $asset->uuid])
            ->assertSee('Photo courtesy of Diocese Network.');
    }

    public function test_alt_text_is_visible(): void
    {
        $asset = $this->ingest('alt.png', 'A congregation gathered for worship');

        Livewire::test(MediaLibraryDetail::class, ['asset' => $asset->uuid])
            ->assertSee('A congregation gathered for worship');
    }

    public function test_alt_text_is_editable_with_manage_capability(): void
    {
        $asset = $this->ingest('editable.png');

        Livewire::test(MediaLibraryDetail::class, ['asset' => $asset->uuid])
            ->callAction('editAltText', data: ['alt_text' => 'Updated description'])
            ->assertHasNoActionErrors();

        $this->assertSame('Updated description', $asset->fresh()->alt_text);
    }

    public function test_viewer_lacking_manage_cannot_mutate_alt_text(): void
    {
        $asset = $this->ingest('locked.png', 'Original alt text');
        $careUser = User::factory()->forChurch($this->church, [ChurchRole::CARE])->create();

        // Care cannot even reach the detail page (no MediaView) — the
        // stronger, page-level denial already proven in the authorization
        // suite. This additionally confirms the action itself is not
        // authorized for a hypothetical viewer without MediaManage.
        $this->assertFalse($careUser->can('update', $asset));
    }

    // ---------------------------------------------------------------
    // §60 — usage
    // ---------------------------------------------------------------

    public function test_brand_reference_is_shown(): void
    {
        $logo = $this->ingest('logo.png');
        ChurchBrandProfile::create(['primary_logo_media_id' => $logo->id]);

        $usage = app(ChurchMediaLibraryQuery::class)->usageFor($logo->fresh());

        $this->assertContains('Used as your primary logo', $usage);
    }

    public function test_brand_mark_reference_is_distinguished_from_primary_logo(): void
    {
        $mark = $this->ingest('mark.png');
        ChurchBrandProfile::create(['mark_media_id' => $mark->id]);

        $usage = app(ChurchMediaLibraryQuery::class)->usageFor($mark->fresh());

        $this->assertContains('Used as your Brand mark', $usage);
    }

    public function test_campaign_reference_is_shown(): void
    {
        $asset = $this->ingest('campaign-art.png');
        $campaign = app(CampaignManager::class)->create(['title' => 'Fall Outreach']);
        app(CampaignMediaManager::class)->attach($campaign, $asset, 'Hero artwork');

        $usage = app(ChurchMediaLibraryQuery::class)->usageFor($asset->fresh());

        $this->assertContains('Used in 1 campaign', $usage);
    }

    public function test_multiple_campaign_references_are_counted(): void
    {
        $asset = $this->ingest('shared-art.png');
        $first = app(CampaignManager::class)->create(['title' => 'Campaign One']);
        $second = app(CampaignManager::class)->create(['title' => 'Campaign Two']);
        app(CampaignMediaManager::class)->attach($first, $asset, 'Artwork');
        app(CampaignMediaManager::class)->attach($second, $asset, 'Artwork');

        $usage = app(ChurchMediaLibraryQuery::class)->usageFor($asset->fresh());

        $this->assertContains('Used in 2 campaigns', $usage);
    }

    public function test_design_selection_reference_is_shown(): void
    {
        $asset = $this->ingest('design-source.png');
        $design = Design::query()->create([
            'template_key' => 'test-template',
            'template_version' => 1,
            'purpose' => DesignPurpose::SERVICE->value,
            'inputs' => [],
            'brand_snapshot' => [],
        ]);
        (new DesignMedia)->forceFill(['design_id' => $design->id, 'media_asset_id' => $asset->id, 'slot_key' => 'background'])->save();

        $usage = app(ChurchMediaLibraryQuery::class)->usageFor($asset->fresh());

        $this->assertContains('Used in Design Studio', $usage);
    }

    public function test_design_generated_output_reference_is_shown(): void
    {
        $design = Design::query()->create([
            'template_key' => 'test-template',
            'template_version' => 1,
            'purpose' => DesignPurpose::SERVICE->value,
            'inputs' => [],
            'brand_snapshot' => [],
        ]);
        $output = MediaAsset::create([
            'disk' => 'media-private',
            'path' => "tenants/{$this->church->id}/media/generated/original.png",
            'original_filename' => 'generated.png',
            'mime_type' => 'image/png',
            'size' => 64,
            'width' => 1080,
            'height' => 1080,
        ]);
        (new DesignOutput)->forceFill(['design_id' => $design->id, 'media_asset_id' => $output->id, 'format' => 'square', 'status' => 'rendered'])->save();

        $usage = app(ChurchMediaLibraryQuery::class)->usageFor($output->fresh());

        $this->assertContains('Used in Design Studio', $usage);
    }

    public function test_unrelated_asset_shows_no_fabricated_usage(): void
    {
        $asset = $this->ingest('unused.png');

        $usage = app(ChurchMediaLibraryQuery::class)->usageFor($asset->fresh());

        $this->assertSame([], $usage);
    }
}
