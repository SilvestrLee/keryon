<?php

namespace Tests\Feature\Trust;

use App\Enums\ChurchRole;
use App\Enums\MediaRenditionState;
use App\Filament\Support\MediaSelectField;
use App\Media\CleanupUnreferencedMediaRenditions;
use App\Media\DeleteMediaAsset;
use App\Media\PrivateMediaDelivery;
use App\Models\Church;
use App\Models\MediaAsset;
use App\Models\MediaPublicReference;
use App\Models\MediaRendition;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class PrivateMediaFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('media-private');
        Storage::fake('media-public');
        $this->church = Church::factory()->create(['slug' => 'private-media']);
        $this->user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->user);
        WebsiteSettings::create();
    }

    public function test_new_upload_is_private_hashed_and_authorized_for_same_church_only(): void
    {
        $staged = $this->stage();
        $asset = MediaSelectField::ingest($staged, 'church.png');

        $this->assertSame('media-private', $asset->disk);
        $this->assertSame(hash('sha256', $this->png()), $asset->sha256);
        Storage::disk('media-private')->assertExists($asset->path);
        Storage::disk('public')->assertMissing($asset->path);
        $this->assertStringNotContainsString($asset->path, app(PrivateMediaDelivery::class)->url($asset));
        $privateResponse = $this->get(route('media.private', $asset->uuid))->assertOk();
        $this->assertStringContainsString('no-store', (string) $privateResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $privateResponse->headers->get('Cache-Control'));

        $other = Church::factory()->create();
        $this->actingAs(User::factory()->forChurch($other, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();
        $this->get(route('media.private', $asset->uuid))->assertNotFound();

        auth()->logout();
        app(TenantContext::class)->forgetResolved();
        $this->get(route('media.private', $asset->uuid))->assertNotFound();
    }

    public function test_legacy_public_asset_remains_available_through_authorized_dual_read(): void
    {
        $asset = $this->asset('public');

        $this->get(route('media.private', $asset->uuid))->assertOk();
        Storage::disk('public')->assertExists($asset->path);
        $this->assertSame('public', $asset->fresh()->disk);
    }

    public function test_website_publish_creates_separate_public_rendition_and_snapshot_identity(): void
    {
        $asset = $this->asset('media-private');
        WebsiteHomeContent::create(['hero_heading' => 'Private becomes deliberate public rendition', 'hero_image_id' => $asset->id]);

        $publication = app(WebsitePublisher::class)->publish();
        $rendition = MediaRendition::query()->sole();

        $this->assertSame($rendition->uuid, $publication->snapshot['public_media']['home.hero']);
        $this->assertNotSame($asset->path, $rendition->path);
        $this->assertStringNotContainsString($asset->uuid, $rendition->path);
        $this->assertSame($asset->sha256, $rendition->source_sha256);
        Storage::disk('media-private')->assertExists($asset->path);
        Storage::disk('media-public')->assertExists($rendition->path);
        $this->get(route('media.public', $rendition->uuid))->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=86400, public');
        $this->get('http://private-media.keryon.app/')->assertOk()
            ->assertSee(route('media.public', $rendition->uuid))
            ->assertDontSee($asset->path)
            ->assertDontSee($asset->uuid);
    }

    public function test_unpublish_deactivates_reference_and_cleanup_obeys_grace_and_is_idempotent(): void
    {
        $asset = $this->asset('media-private');
        WebsiteHomeContent::create(['hero_image_id' => $asset->id]);
        app(WebsitePublisher::class)->publish();
        $rendition = MediaRendition::query()->sole();

        app(WebsitePublisher::class)->unpublish();
        $this->assertNotNull($rendition->publicReferences()->sole()->deactivated_at);
        $this->get(route('media.public', $rendition->uuid))->assertNotFound();
        $this->assertSame(0, app(CleanupUnreferencedMediaRenditions::class)->handle());
        Storage::disk('media-public')->assertExists($rendition->path);

        $this->travel(25)->hours();
        $this->assertSame(1, app(CleanupUnreferencedMediaRenditions::class)->handle());
        $this->assertSame(MediaRenditionState::Revoked, $rendition->fresh()->state);
        Storage::disk('media-public')->assertMissing($rendition->path);
        Storage::disk('media-private')->assertExists($asset->path);
        $this->assertSame(0, app(CleanupUnreferencedMediaRenditions::class)->handle());
        $this->get(route('media.public', $rendition->uuid))->assertNotFound();
    }

    public function test_republication_reuses_rendition_and_an_active_reference_prevents_cleanup(): void
    {
        $asset = $this->asset('media-private');
        WebsiteHomeContent::create(['hero_image_id' => $asset->id]);
        $first = app(WebsitePublisher::class)->publish();
        $rendition = MediaRendition::query()->sole();

        $second = app(WebsitePublisher::class)->publish();

        $this->assertSame(1, MediaRendition::query()->count());
        $this->assertNotNull(MediaPublicReference::query()->where('consumer_id', $first->id)->sole()->deactivated_at);
        $this->assertNull(MediaPublicReference::query()->where('consumer_id', $second->id)->sole()->deactivated_at);

        $this->travel(25)->hours();
        $this->assertSame(0, app(CleanupUnreferencedMediaRenditions::class)->handle());
        $this->assertSame(MediaRenditionState::Active, $rendition->fresh()->state);
        Storage::disk('media-public')->assertExists($rendition->path);
    }

    public function test_failed_publication_preserves_previous_active_publication(): void
    {
        $asset = $this->asset('media-private');
        WebsiteHomeContent::create(['hero_image_id' => $asset->id]);
        $first = app(WebsitePublisher::class)->publish();
        $reference = MediaPublicReference::query()->sole();

        Storage::disk('media-private')->put($asset->path, $this->png().'corrupt');

        try {
            app(WebsitePublisher::class)->publish();
            $this->fail('Publishing corrupted canonical bytes should fail.');
        } catch (ValidationException) {
            $this->assertSame($first->id, WebsiteSettings::query()->sole()->current_publication_id);
            $this->assertNull($reference->fresh()->deactivated_at);
            $this->assertSame(1, MediaPublicReference::query()->count());
        }
    }

    public function test_active_public_reference_blocks_delete_and_restore_never_republishes(): void
    {
        $asset = $this->asset('media-private');
        WebsiteHomeContent::create(['hero_image_id' => $asset->id]);
        app(WebsitePublisher::class)->publish();

        $this->expectException(LogicException::class);
        app(DeleteMediaAsset::class)->handle($asset);
    }

    public function test_delete_after_unpublish_is_logical_and_restore_requires_integrity(): void
    {
        $asset = $this->asset('media-private');
        WebsiteHomeContent::create(['hero_image_id' => $asset->id]);
        app(WebsitePublisher::class)->publish();
        app(WebsitePublisher::class)->unpublish();

        app(DeleteMediaAsset::class)->handle($asset);
        $this->assertSoftDeleted($asset);
        Storage::disk('media-private')->assertExists($asset->path);

        app(DeleteMediaAsset::class)->restore($asset);
        $this->assertNull($asset->fresh()->deleted_at);
        $this->assertNotNull($asset->renditions()->sole()->publicReferences()->sole()->deactivated_at);
    }

    private function stage(): string
    {
        $path = "tenants/{$this->church->id}/media/.staging/".uniqid().'.tmp';
        Storage::disk('media-private')->put($path, $this->png());

        return $path;
    }

    private function asset(string $disk): MediaAsset
    {
        $uuid = (string) Str::uuid();
        $path = "tenants/{$this->church->id}/media/{$uuid}/original.png";
        Storage::disk($disk)->put($path, $this->png());
        $asset = new MediaAsset([
            'disk' => $disk,
            'path' => $path,
            'original_filename' => 'church.png',
            'mime_type' => 'image/png',
            'size' => strlen($this->png()),
            'sha256' => hash('sha256', $this->png()),
            'width' => 1,
            'height' => 1,
        ]);
        $asset->uuid = $uuid;
        $asset->save();

        return $asset;
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
