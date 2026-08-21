<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Filament\Support\MediaSelectField;
use App\Models\Church;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * K-CHURCHWEB-001C-R1 §12 — regression coverage for the corrected
 * institutional-media storage contract. Exercises `MediaSelectField::ingest()`
 * directly (real file bytes, a real fake disk) rather than driving
 * Filament's `createOptionForm` action machinery, per the directive's own
 * "do not create brittle tests tied to incidental Filament HTML"
 * instruction — this proves the actual storage contract Keryon owns,
 * without re-testing Filament's own (separately well-tested) upload
 * validation engine.
 */
class MediaIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('public');
    }

    /**
     * A minimal but genuinely valid 1x1 PNG — real bytes, so
     * `getimagesize()` inside ingest() succeeds exactly as it would for a
     * real upload.
     */
    protected function fakePngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    protected function stageUpload(Church $church, string $filename, ?string $bytes = null): string
    {
        $stagingPath = "tenants/{$church->id}/media/.staging/".uniqid().'.tmp';
        Storage::disk('public')->put($stagingPath, $bytes ?? $this->fakePngBytes());

        return $stagingPath;
    }

    protected function commsUserFor(Church $church): User
    {
        return User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
    }

    public function test_ingest_stores_the_asset_under_the_canonical_tenant_uuid_path(): void
    {
        $church = Church::create(['name' => 'Canonical Path Church', 'slug' => 'canonical-path-church']);
        $this->actingAs($this->commsUserFor($church));

        $staged = $this->stageUpload($church, 'hero.png');

        $asset = MediaSelectField::ingest($staged, 'hero.png');

        $this->assertSame("tenants/{$church->id}/media/{$asset->uuid}/original.png", $asset->path);
        Storage::disk('public')->assertExists($asset->path);
        // The staging copy must be gone — moved, not duplicated.
        Storage::disk('public')->assertMissing($staged);
    }

    public function test_church_a_and_church_b_uploads_never_share_an_asset_namespace(): void
    {
        $churchA = Church::create(['name' => 'Namespace Church A', 'slug' => 'namespace-church-a']);
        $churchB = Church::create(['name' => 'Namespace Church B', 'slug' => 'namespace-church-b']);

        $this->actingAs($this->commsUserFor($churchA));
        $stagedA = $this->stageUpload($churchA, 'logo.png');
        $assetA = MediaSelectField::ingest($stagedA, 'logo.png');

        $this->actingAs($this->commsUserFor($churchB));
        $stagedB = $this->stageUpload($churchB, 'logo.png');
        $assetB = MediaSelectField::ingest($stagedB, 'logo.png');

        $this->assertStringStartsWith("tenants/{$churchA->id}/media/", $assetA->path);
        $this->assertStringStartsWith("tenants/{$churchB->id}/media/", $assetB->path);
        $this->assertNotSame($assetA->path, $assetB->path);
        $this->assertNotSame($assetA->uuid, $assetB->uuid);
    }

    public function test_two_uploads_with_the_same_original_filename_do_not_overwrite_each_other(): void
    {
        $church = Church::create(['name' => 'Collision Church', 'slug' => 'collision-church']);
        $this->actingAs($this->commsUserFor($church));

        $stagedFirst = $this->stageUpload($church, 'logo.png');
        $first = MediaSelectField::ingest($stagedFirst, 'logo.png');

        $stagedSecond = $this->stageUpload($church, 'logo.png');
        $second = MediaSelectField::ingest($stagedSecond, 'logo.png');

        $this->assertNotSame($first->path, $second->path);
        Storage::disk('public')->assertExists($first->path);
        Storage::disk('public')->assertExists($second->path);
        // Both still remember the same human filename as metadata —
        // that never had to be unique.
        $this->assertSame('logo.png', $first->original_filename);
        $this->assertSame('logo.png', $second->original_filename);
    }

    public function test_original_filename_is_preserved_independently_of_the_normalized_storage_path(): void
    {
        $church = Church::create(['name' => 'Filename Church', 'slug' => 'filename-church']);
        $this->actingAs($this->commsUserFor($church));

        $staged = $this->stageUpload($church, 'Sunday Service Banner.png');
        $asset = MediaSelectField::ingest($staged, 'Sunday Service Banner.png');

        $this->assertSame('Sunday Service Banner.png', $asset->original_filename);
        $this->assertStringNotContainsString('Sunday Service Banner', $asset->path);
        $this->assertStringEndsWith('/original.png', $asset->path);
    }

    public function test_ingested_asset_captures_real_detected_metadata(): void
    {
        $church = Church::create(['name' => 'Metadata Church', 'slug' => 'metadata-church']);
        $this->actingAs($this->commsUserFor($church));

        $staged = $this->stageUpload($church, 'photo.png');
        $asset = MediaSelectField::ingest($staged, 'photo.png', 'A sanctuary photo.');

        $this->assertSame($church->id, $asset->church_id);
        $this->assertSame('image/png', $asset->mime_type);
        $this->assertGreaterThan(0, $asset->size);
        $this->assertSame(1, $asset->width);
        $this->assertSame(1, $asset->height);
        $this->assertSame('A sanctuary photo.', $asset->alt_text);
    }

    public function test_ingested_asset_can_be_selected_by_website_content_afterward(): void
    {
        $church = Church::create(['name' => 'Selectable Church', 'slug' => 'selectable-church']);
        $this->actingAs($this->commsUserFor($church));

        $staged = $this->stageUpload($church, 'hero.png');
        $asset = MediaSelectField::ingest($staged, 'hero.png');

        $home = WebsiteHomeContent::create(['hero_image_id' => $asset->id]);

        $this->assertSame($asset->id, $home->fresh()->hero_image_id);
    }

    public function test_existing_asset_is_available_to_the_same_church_picker(): void
    {
        $church = Church::create(['name' => 'Reuse Church', 'slug' => 'reuse-church']);
        $this->actingAs($this->commsUserFor($church));
        $staged = $this->stageUpload($church, 'library-photo.png');
        $asset = MediaSelectField::ingest($staged, 'library-photo.png');

        $options = MediaSelectField::make('hero_image_id', 'Hero image')->getOptions();

        $this->assertSame('library-photo.png', $options[$asset->id]);
    }

    public function test_accepted_mime_type_policy_excludes_svg_and_allows_only_standard_web_raster_formats(): void
    {
        $this->assertSame(
            ['image/jpeg', 'image/png', 'image/webp'],
            MediaSelectField::ACCEPTED_MIME_TYPES
        );
        $this->assertNotContains('image/svg+xml', MediaSelectField::ACCEPTED_MIME_TYPES);
    }

    public function test_maximum_upload_size_is_bounded(): void
    {
        $this->assertSame(10240, MediaSelectField::MAX_UPLOAD_SIZE_KB);
    }

    public function test_non_image_upload_is_rejected_with_form_validation(): void
    {
        $church = Church::create(['name' => 'Invalid Type Church', 'slug' => 'invalid-type-church']);
        $this->actingAs($this->commsUserFor($church));
        $staged = $this->stageUpload($church, 'notes.txt', 'not an image');

        try {
            MediaSelectField::ingest($staged, 'notes.txt');
            $this->fail('The non-image upload was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('upload', $exception->errors());
            $this->assertCount(0, MediaAsset::withoutGlobalScopes()->get());
        }
    }

    public function test_svg_upload_is_explicitly_rejected(): void
    {
        $church = Church::create(['name' => 'SVG Church', 'slug' => 'svg-church']);
        $this->actingAs($this->commsUserFor($church));
        $staged = $this->stageUpload($church, 'logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->expectException(ValidationException::class);
        MediaSelectField::ingest($staged, 'logo.svg');
    }

    public function test_upload_larger_than_the_configured_maximum_is_rejected(): void
    {
        $church = Church::create(['name' => 'Large Image Church', 'slug' => 'large-image-church']);
        $this->actingAs($this->commsUserFor($church));
        $staged = $this->stageUpload($church, 'large.png', $this->fakePngBytes());
        Storage::disk('public')->append($staged, str_repeat('x', (MediaSelectField::MAX_UPLOAD_SIZE_KB * 1024) + 1));

        $this->expectException(ValidationException::class);
        MediaSelectField::ingest($staged, 'large.png');
    }

    public function test_ingest_rejects_a_staging_path_owned_by_another_church(): void
    {
        $churchA = Church::create(['name' => 'Path Church A', 'slug' => 'path-church-a']);
        $churchB = Church::create(['name' => 'Path Church B', 'slug' => 'path-church-b']);
        $staged = $this->stageUpload($churchA, 'hero.png');
        $this->actingAs($this->commsUserFor($churchB));

        $this->expectException(ValidationException::class);
        MediaSelectField::ingest($staged, 'hero.png');
    }

    public function test_cross_church_media_reference_rejection_remains_intact_after_the_path_correction(): void
    {
        $churchA = Church::create(['name' => 'Regression Church A', 'slug' => 'regression-church-a']);
        $churchB = Church::create(['name' => 'Regression Church B', 'slug' => 'regression-church-b']);

        $this->actingAs($this->commsUserFor($churchA));
        $stagedA = $this->stageUpload($churchA, 'logo.png');
        $assetA = MediaSelectField::ingest($stagedA, 'logo.png');

        $this->actingAs($this->commsUserFor($churchB));

        $this->expectException(\InvalidArgumentException::class);

        WebsiteHomeContent::create(['hero_image_id' => $assetA->id]);
    }
}
