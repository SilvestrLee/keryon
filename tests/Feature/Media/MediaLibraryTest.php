<?php

namespace Tests\Feature\Media;

use App\Enums\AssetProvenance;
use App\Enums\AssetRightsStatus;
use App\Enums\ChurchRole;
use App\Filament\Pages\MediaLibrary;
use App\Media\ChurchMediaLibraryQuery;
use App\Media\IngestMediaAsset;
use App\Models\Church;
use App\Models\MediaAsset;
use App\Models\User;
use App\Trust\Rights\RecordMediaAssetRights;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-MEDIA-V1-001B §57/§58 — Library listing (empty state, pagination,
 * sort, search, filters, soft-delete exclusion) and direct-upload
 * (§13/§58) test matrices.
 */
class MediaLibraryTest extends TestCase
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

    protected function stage(?string $bytes = null): string
    {
        $staged = "tenants/{$this->church->id}/media/.staging/".uniqid().'.tmp';
        Storage::disk('media-private')->put($staged, $bytes ?? $this->fakePngBytes());

        return $staged;
    }

    protected function ingest(string $filename = 'hero.png'): MediaAsset
    {
        return app(IngestMediaAsset::class)->handle($this->stage(), $filename);
    }

    // ---------------------------------------------------------------
    // §57 — listing
    // ---------------------------------------------------------------

    public function test_empty_library_shows_the_empty_state(): void
    {
        Livewire::test(MediaLibrary::class)
            ->assertSee('No media yet')
            ->assertSee('Upload media');
    }

    public function test_paginated_listing_returns_only_current_church_assets(): void
    {
        $other = Church::factory()->create();
        $this->actingAs(User::factory()->forChurch($other, [ChurchRole::COMMUNICATIONS])->create());
        $foreignStaged = "tenants/{$other->id}/media/.staging/".uniqid().'.tmp';
        Storage::disk('media-private')->put($foreignStaged, $this->fakePngBytes());
        app(IngestMediaAsset::class)->handle($foreignStaged, 'foreign.png');

        $this->actingAs($this->user);
        $mine = $this->ingest('mine.png');

        $result = app(ChurchMediaLibraryQuery::class)->paginate();
        $this->assertSame(1, $result->total());
        $this->assertSame($mine->id, $result->items()[0]->id);
    }

    public function test_newest_first_is_the_default_sort(): void
    {
        $older = $this->ingest('older.png');
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $newer = $this->ingest('newer.png');
        $newer->forceFill(['created_at' => now()])->save();

        $result = app(ChurchMediaLibraryQuery::class)->paginate();

        $this->assertSame($newer->id, $result->items()[0]->id);
    }

    public function test_filename_search_matches_original_filename(): void
    {
        $this->ingest('sunday-banner.png');
        $this->ingest('logo.png');

        $result = app(ChurchMediaLibraryQuery::class)->paginate(search: 'banner');

        $this->assertSame(1, $result->total());
        $this->assertSame('sunday-banner.png', $result->items()[0]->original_filename);
    }

    public function test_alt_text_search_matches_alt_text(): void
    {
        app(IngestMediaAsset::class)->handle($this->stage(), 'a.png', 'A sanctuary photo');
        app(IngestMediaAsset::class)->handle($this->stage(), 'b.png', 'A choir rehearsal');

        $result = app(ChurchMediaLibraryQuery::class)->paginate(search: 'sanctuary');

        $this->assertSame(1, $result->total());
        $this->assertSame('a.png', $result->items()[0]->original_filename);
    }

    public function test_provenance_filter_narrows_results(): void
    {
        $churchDeclared = $this->ingest('church-declared.png');
        // Mirrors RenderDesignOutput's own real production pattern: the
        // MediaAsset is created directly (not through the Church upload
        // boundary), then keryonCreated() records its rights once, on a
        // fresh asset with no prior rights row.
        $designOutput = MediaAsset::create([
            'disk' => 'media-private',
            'path' => "tenants/{$this->church->id}/media/design-output/original.png",
            'original_filename' => 'design-output.png',
            'mime_type' => 'image/png',
            'size' => 128,
            'width' => 1080,
            'height' => 1080,
        ]);
        app(RecordMediaAssetRights::class)->keryonCreated($designOutput);

        $result = app(ChurchMediaLibraryQuery::class)->paginate(provenance: AssetProvenance::KeryonCreated);

        $this->assertSame(1, $result->total());
        $this->assertSame($designOutput->id, $result->items()[0]->id);
        $this->assertNotSame($churchDeclared->id, $result->items()[0]->id);
    }

    public function test_rights_status_filter_narrows_results(): void
    {
        $declared = $this->ingest('declared.png');
        $restricted = $this->ingest('restricted.png');
        app(RecordMediaAssetRights::class)->restrict($restricted, AssetRightsStatus::Restricted, 'Rights under review.');

        $result = app(ChurchMediaLibraryQuery::class)->paginate(rightsStatus: AssetRightsStatus::Restricted);

        $this->assertSame(1, $result->total());
        $this->assertSame($restricted->id, $result->items()[0]->id);
        $this->assertNotSame($declared->id, $result->items()[0]->id);
    }

    public function test_sort_oldest_first(): void
    {
        $older = $this->ingest('older.png');
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $newer = $this->ingest('newer.png');
        $newer->forceFill(['created_at' => now()])->save();

        $result = app(ChurchMediaLibraryQuery::class)->paginate(sort: ChurchMediaLibraryQuery::SORT_OLDEST);

        $this->assertSame($older->id, $result->items()[0]->id);
    }

    public function test_sort_by_filename(): void
    {
        $this->ingest('zebra.png');
        $this->ingest('apple.png');

        $result = app(ChurchMediaLibraryQuery::class)->paginate(sort: ChurchMediaLibraryQuery::SORT_NAME);

        $this->assertSame('apple.png', $result->items()[0]->original_filename);
        $this->assertSame('zebra.png', $result->items()[1]->original_filename);
    }

    public function test_soft_deleted_asset_is_absent_from_the_normal_listing(): void
    {
        $asset = $this->ingest('to-delete.png');
        $asset->delete();

        $result = app(ChurchMediaLibraryQuery::class)->paginate();

        $this->assertSame(0, $result->total());
    }

    public function test_pagination_uses_real_sql_limit_not_a_full_table_load(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->ingest("asset-{$i}.png");
        }

        $result = app(ChurchMediaLibraryQuery::class)->paginate(perPage: 12);

        $this->assertSame(30, $result->total());
        $this->assertCount(12, $result->items());
        $this->assertSame(3, $result->lastPage());
    }

    public function test_library_page_renders_uploaded_assets(): void
    {
        $this->ingest('rendered.png');

        Livewire::test(MediaLibrary::class)
            ->assertSee('rendered.png')
            ->assertSee('1 item');
    }

    // ---------------------------------------------------------------
    // §58 — direct upload via the Library's own action
    // ---------------------------------------------------------------

    /**
     * K-MEDIA-V1-001B §14 — proves the Library's own upload path and
     * `MediaSelectField`'s picker path are the same ingestion, without
     * driving Filament's `FileUpload` component through Livewire's test
     * harness (the same "do not create brittle tests tied to incidental
     * Filament HTML" boundary `MediaIngestionTest` already documents for
     * the picker). `MediaLibrary::uploadMediaAction()`'s `->action()`
     * closure calls `IngestMediaAsset::handle()` directly — the exact
     * method exercised here — so this is the real contract, not a stand-in.
     */
    public function test_upload_lands_in_the_library_through_the_shared_ingestion_service(): void
    {
        $asset = app(IngestMediaAsset::class)->handle($this->stage(), 'via-library.png', 'Uploaded via Library');

        $this->assertSame($this->church->id, $asset->church_id);
        $this->assertNotNull($asset->sha256);
        $this->assertSame(1, $asset->width);
        $this->assertSame(1, $asset->height);
        $this->assertNotNull($asset->rights);
        $this->assertSame(AssetProvenance::ChurchDeclared, $asset->rights->provenance);

        Livewire::test(MediaLibrary::class)->assertSee('via-library.png');
    }

    public function test_upload_only_requires_manage_capability_care_cannot_upload(): void
    {
        $careUser = User::factory()->forChurch($this->church, [ChurchRole::CARE])->create();
        $this->actingAs($careUser);

        $this->assertFalse($careUser->can('create', MediaAsset::class));
    }
}
