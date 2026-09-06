<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\AssetRightsStatus;
use App\Enums\AssetUse;
use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\ChurchBrandProfile;
use App\Models\MediaAsset;
use App\Models\MediaAssetRights;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * K-WEB-V1-001C-B — the approved v1 favicon contract: the Church's
 * existing Brand mark (`ChurchBrandProfile.mark_media_id`), reused as-is,
 * with a `<link rel="icon">` added to the Proclaim head. No new field, no
 * new rendition variant, no favicon-specific rights policy — every
 * scenario here exercises machinery that already exists for the mark's
 * other use (Open Graph image), per the directive's own instruction that
 * this milestone must prove reuse rather than introduce anything new.
 */
class WebsiteFaviconTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('media-private');
        Storage::fake('media-public');

        $this->church = Church::create([
            'name' => 'Favicon Test Church', 'slug' => 'favicon-test-church',
            'email' => 'hello@favicon.test', 'phone' => '111', 'address' => 'One address',
        ]);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        WebsiteSettings::create(['theme' => 'proclaim']);
        WebsiteHomeContent::create(['hero_heading' => 'Welcome']);
    }

    private function fakePngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    /** A distinct, genuinely different valid 1x1 PNG (red pixel). */
    private function alternatePngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    /**
     * A "public"-disk asset, matching `PublicMedia::image()`'s existing
     * preview-path constraint (§ constraint noted in K-WEB-V1-001C-A —
     * out of scope to change here).
     */
    private function previewableAsset(string $bytes, string $marker = 'mark'): MediaAsset
    {
        $uuid = (string) Str::uuid();
        $path = "tenants/{$this->church->id}/media/{$uuid}/original.png";
        Storage::disk('public')->put($path, $bytes);
        $asset = new MediaAsset([
            'disk' => 'public', 'path' => $path, 'original_filename' => "{$marker}.png",
            'mime_type' => 'image/png', 'size' => strlen($bytes), 'width' => 1, 'height' => 1,
            'alt_text' => $marker,
        ]);
        $asset->uuid = $uuid;
        $asset->save();

        return $asset;
    }

    private function publicGet(string $path = '/')
    {
        return $this->get("http://favicon-test-church.keryon.app{$path}");
    }

    // ---------------------------------------------------------------
    // §24 — favicon present
    // ---------------------------------------------------------------

    public function test_a_configured_mark_renders_a_public_favicon_link_pointing_at_a_public_rendition(): void
    {
        $mark = $this->previewableAsset($this->fakePngBytes());
        ChurchBrandProfile::create(['mark_media_id' => $mark->id]);
        app(WebsitePublisher::class)->publish();

        $response = $this->publicGet();

        $response->assertOk();
        $rendition = $mark->renditions()->first();
        $this->assertNotNull($rendition, 'Publishing with a mark configured must create the existing WebOriginal rendition.');
        $expectedUrl = config('public-website.asset_origin').'/media/'.$rendition->uuid;
        $response->assertSee('<link rel="icon" href="'.$expectedUrl.'">', false);
        // Never a private delivery route or a raw private disk path.
        $response->assertDontSee('media.private', false);
        $response->assertDontSee($mark->path);
    }

    // ---------------------------------------------------------------
    // §25 — favicon absent, no fallback to the primary logo
    // ---------------------------------------------------------------

    public function test_a_church_with_only_a_primary_logo_and_no_mark_renders_no_favicon_link(): void
    {
        $logo = $this->previewableAsset($this->fakePngBytes(), 'logo');
        ChurchBrandProfile::create(['primary_logo_media_id' => $logo->id]);
        app(WebsitePublisher::class)->publish();

        $response = $this->publicGet();

        $response->assertOk();
        $response->assertDontSee('rel="icon"', false);
    }

    public function test_a_church_with_no_brand_profile_at_all_renders_no_favicon_link(): void
    {
        app(WebsitePublisher::class)->publish();

        $this->publicGet()->assertOk()->assertDontSee('rel="icon"', false);
    }

    // ---------------------------------------------------------------
    // §26 — historical immutability across publish / change / republish
    // ---------------------------------------------------------------

    public function test_favicon_is_immutable_per_publication_and_survives_an_unpublished_mark_change(): void
    {
        $markA = $this->previewableAsset($this->fakePngBytes(), 'mark-a');
        $brand = ChurchBrandProfile::create(['mark_media_id' => $markA->id]);
        $firstPublication = app(WebsitePublisher::class)->publish();
        $firstRenditionUuid = $markA->renditions()->first()->uuid;
        $firstFaviconUrl = config('public-website.asset_origin').'/media/'.$firstRenditionUuid;

        $this->publicGet()->assertSee('<link rel="icon" href="'.$firstFaviconUrl.'">', false);

        // Change the Brand mark without republishing.
        $markB = $this->previewableAsset($this->alternatePngBytes(), 'mark-b');
        $brand->update(['mark_media_id' => $markB->id]);
        app(TenantContext::class)->forgetResolved();

        $this->publicGet()->assertSee('<link rel="icon" href="'.$firstFaviconUrl.'">', false);

        // Republish — the live site now shows the new mark.
        app(WebsitePublisher::class)->publish();
        $secondRenditionUuid = $markB->renditions()->first()->uuid;
        $secondFaviconUrl = config('public-website.asset_origin').'/media/'.$secondRenditionUuid;
        $this->assertNotSame($firstFaviconUrl, $secondFaviconUrl);

        $this->publicGet()
            ->assertSee('<link rel="icon" href="'.$secondFaviconUrl.'">', false)
            ->assertDontSee($firstFaviconUrl);

        // The *first* publication's own stored snapshot still resolves
        // to Mark A — historical publications are never rewritten.
        $this->assertSame(
            $firstFaviconUrl,
            config('public-website.asset_origin').'/media/'.($firstPublication->fresh()->snapshot['public_media']['brand.mark'] ?? null),
        );
    }

    // ---------------------------------------------------------------
    // §27 — rights-restricted mark cannot become a favicon; no
    // favicon-specific policy exists, this is the pre-existing
    // `AssetRightsPolicy`/`PublicMediaRenditionManager` gate publish()
    // already applies to every usage, including `brand.mark`.
    // ---------------------------------------------------------------

    public function test_publishing_a_rights_restricted_mark_is_rejected_by_the_existing_rights_gate(): void
    {
        $mark = $this->previewableAsset($this->fakePngBytes());
        MediaAssetRights::create([
            'church_id' => $this->church->id,
            'media_asset_id' => $mark->id,
            'provenance' => 'church_declared',
            'status' => AssetRightsStatus::Restricted,
            'allowed_uses' => [AssetUse::Store->value, AssetUse::InternalUse->value],
            'restriction_reason' => 'Under legal review.',
            'attribution_required' => false,
            'declared_at' => now(),
        ]);
        ChurchBrandProfile::create(['mark_media_id' => $mark->id]);

        $this->expectException(ValidationException::class);
        app(WebsitePublisher::class)->publish();
    }

    // ---------------------------------------------------------------
    // §28 — preview graceful degradation
    // ---------------------------------------------------------------

    public function test_preview_renders_with_a_favicon_when_the_mark_is_preview_eligible(): void
    {
        $mark = $this->previewableAsset($this->fakePngBytes());
        ChurchBrandProfile::create(['mark_media_id' => $mark->id]);

        $response = $this->get(route('website.preview'));

        $response->assertOk();
        $response->assertSee('noindex, nofollow');
        $response->assertSee('<link rel="icon" href="'.Storage::disk('public')->url($mark->path).'">', false);
    }

    public function test_preview_degrades_gracefully_with_no_favicon_when_the_mark_is_not_preview_eligible(): void
    {
        // A private-disk asset — the pre-existing, out-of-scope
        // `PublicMedia::image()` preview constraint (`disk === 'public'`
        // only) means this mark simply does not resolve for preview;
        // the preview must still render fully, without error, and
        // without a favicon tag — never a private URL.
        Storage::disk('media-private')->put("tenants/{$this->church->id}/media/private-mark/original.png", $this->fakePngBytes());
        $mark = MediaAsset::create([
            'disk' => 'media-private', 'path' => "tenants/{$this->church->id}/media/private-mark/original.png",
            'original_filename' => 'private-mark.png', 'mime_type' => 'image/png', 'size' => 64,
            'width' => 1, 'height' => 1, 'sha256' => hash('sha256', $this->fakePngBytes()),
        ]);
        ChurchBrandProfile::create(['mark_media_id' => $mark->id]);

        $response = $this->get(route('website.preview'));

        $response->assertOk();
        $response->assertSee('noindex, nofollow');
        $response->assertDontSee('rel="icon"', false);
    }
}
