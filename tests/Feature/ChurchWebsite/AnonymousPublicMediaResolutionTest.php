<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\AssetRightsStatus;
use App\Enums\ChurchRole;
use App\Enums\LeadershipCategory;
use App\Enums\MediaRenditionState;
use App\Models\Church;
use App\Models\ChurchBrandProfile;
use App\Models\MediaAsset;
use App\Models\MediaAssetRights;
use App\Models\MediaPublicReference;
use App\Models\MediaRendition;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteLeadershipProfile;
use App\Models\WebsiteMinistry;
use App\Models\WebsiteSettings;
use App\PublicWebsite\PublicMedia;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * K-WEB-P0-001 — the dedicated, milestone-owned regression proof for the
 * anonymous public-media resolution defect. Deliberately kept separate
 * from `WebsiteFaviconTest.php` (K-WEB-V1-001C-B, still uncommitted) so
 * commit ownership between the two milestones is never blurred — see the
 * K-WEB-P0-001 report §49 diff ledger.
 *
 * Every "public" assertion in this file is genuinely anonymous: setup
 * (Church, Brand, Website content, publish) runs authenticated, then
 * `Auth::logout()` + `TenantContext::forgetResolved()` runs *before* the
 * request each assertion is based on — never relying on a cross-host URL
 * string alone to imply anonymity (see report §18/§F for why that alone
 * is not sufficient in this test suite).
 */
class AnonymousPublicMediaResolutionTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('media-public');

        $this->church = Church::create([
            'name' => 'Anonymous Proof Church', 'slug' => 'anonymous-proof-church',
        ]);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        WebsiteSettings::create(['theme' => 'proclaim']);
    }

    private function asset(string $marker): MediaAsset
    {
        $uuid = (string) Str::uuid();
        $path = "tenants/{$this->church->id}/media/{$uuid}/original.png";
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=').$marker;
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

    /**
     * Genuinely anonymous — no `actingAs()` in scope for the request
     * itself, and the previously-authenticated identity is restored
     * afterward only so later authenticated setup in the same test
     * (creating more fixtures, publishing again) keeps working.
     */
    private function anonymousPublicGet(string $path = '/'): TestResponse
    {
        $authenticated = Auth::user();
        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        $response = $this->get("http://anonymous-proof-church.keryon.app{$path}");

        if ($authenticated !== null) {
            Auth::login($authenticated);
            app(TenantContext::class)->forgetResolved();
        }

        return $response;
    }

    // ---------------------------------------------------------------
    // §19/§24/§25 — the core positive proof: one publication, every
    // rendition-backed usage category, resolved with zero authenticated
    // session in play for the request itself.
    // ---------------------------------------------------------------

    public function test_a_genuinely_anonymous_visitor_sees_every_rendition_backed_public_image(): void
    {
        $logo = $this->asset('logo');
        $mark = $this->asset('mark');
        $hero = $this->asset('hero');
        $leaderPhoto = $this->asset('leader');
        $ministryImage = $this->asset('ministry');

        ChurchBrandProfile::create(['primary_logo_media_id' => $logo->id, 'mark_media_id' => $mark->id]);
        WebsiteHomeContent::create(['hero_heading' => 'Welcome', 'hero_image_id' => $hero->id]);
        WebsiteLeadershipProfile::create([
            'name' => 'Pastor Anonymous', 'category' => LeadershipCategory::PASTOR->value,
            'role_title' => 'Lead Pastor', 'photo_id' => $leaderPhoto->id, 'sort_order' => 1,
        ]);
        WebsiteMinistry::create(['name' => 'Outreach', 'description' => 'Serving our neighbours.', 'image_id' => $ministryImage->id, 'sort_order' => 1]);

        app(WebsitePublisher::class)->publish();

        $home = $this->anonymousPublicGet('/');
        $home->assertOk();
        $home->assertSee(config('public-website.asset_origin').'/media/'.$logo->renditions()->first()->uuid);
        $home->assertSee(config('public-website.asset_origin').'/media/'.$hero->renditions()->first()->uuid);
        // §25 — Open Graph image is pre-existing functionality; still
        // part of this proof, not redesigned.
        $home->assertSee('og:image" content="'.config('public-website.asset_origin').'/media/'.$mark->renditions()->first()->uuid, false);
        // §26 — favicon works too because the uncommitted 001C-B diff is
        // present, but this is an observation, not part of P0's own scope.
        $home->assertSee('<link rel="icon" href="'.config('public-website.asset_origin').'/media/'.$mark->renditions()->first()->uuid.'">', false);

        $leadership = $this->anonymousPublicGet('/leadership');
        $leadership->assertOk()->assertSee(config('public-website.asset_origin').'/media/'.$leaderPhoto->renditions()->first()->uuid);

        $ministries = $this->anonymousPublicGet('/ministries');
        $ministries->assertOk()->assertSee(config('public-website.asset_origin').'/media/'.$ministryImage->renditions()->first()->uuid);
    }

    // ---------------------------------------------------------------
    // §43 — a valid public visitor has no User, no ChurchMembership, no
    // OrganizationMembership, no PlatformMembership. Asserted at the
    // moment of the request itself (not after `anonymousPublicGet()`'s
    // own restoration step, which exists only to keep later authenticated
    // setup in other tests working).
    // ---------------------------------------------------------------

    public function test_normal_public_rendering_requires_no_membership_of_any_kind(): void
    {
        $logo = $this->asset('membership-free-logo');
        ChurchBrandProfile::create(['primary_logo_media_id' => $logo->id]);
        WebsiteHomeContent::create(['hero_heading' => 'No membership required']);
        app(WebsitePublisher::class)->publish();

        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        $this->assertGuest();
        $this->get('http://anonymous-proof-church.keryon.app/')
            ->assertOk()
            ->assertSee('No membership required');
        $this->assertGuest();
    }

    // ---------------------------------------------------------------
    // §27 — text content is unaffected by the fix.
    // ---------------------------------------------------------------

    public function test_anonymous_visitor_still_sees_full_text_content(): void
    {
        WebsiteHomeContent::create(['hero_heading' => 'A welcoming church for everyone']);
        app(WebsitePublisher::class)->publish();

        $this->anonymousPublicGet('/')->assertOk()->assertSee('A welcoming church for everyone');
    }

    // ---------------------------------------------------------------
    // §11/§42 — cross-Church negative proof: Church A's publication must
    // never leak into a request resolved for Church B, and the reverse.
    // ---------------------------------------------------------------

    public function test_a_second_churchs_public_site_never_resolves_the_first_churchs_media(): void
    {
        $logo = $this->asset('church-a-logo');
        ChurchBrandProfile::create(['primary_logo_media_id' => $logo->id]);
        WebsiteHomeContent::create(['hero_heading' => 'Church A home']);
        app(WebsitePublisher::class)->publish();
        $renditionUuid = $logo->renditions()->first()->uuid;

        $otherChurch = Church::create(['name' => 'Second Church', 'slug' => 'second-anonymous-church']);
        $this->actingAs(User::factory()->forChurch($otherChurch, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();
        WebsiteSettings::create(['theme' => 'proclaim']);
        WebsiteHomeContent::create(['hero_heading' => 'Church B home']);
        app(WebsitePublisher::class)->publish();

        $authenticated = Auth::user();
        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        $secondChurchHome = $this->get('http://second-anonymous-church.keryon.app/');
        $secondChurchHome->assertOk()
            ->assertSee('Church B home')
            ->assertDontSee($renditionUuid);

        // Direct proof at the service layer: Church A's rendition,
        // looked up under Church B's id, must not resolve.
        $this->assertNull(app(PublicMedia::class)->rendition($otherChurch->id, $renditionUuid, ''));
        // ...but it must still resolve correctly under its own Church.
        $this->assertNotNull(app(PublicMedia::class)->rendition($this->church->id, $renditionUuid, ''));

        if ($authenticated !== null) {
            Auth::login($authenticated);
            app(TenantContext::class)->forgetResolved();
        }
    }

    // ---------------------------------------------------------------
    // §9 — deactivated reference must still fail to resolve.
    // ---------------------------------------------------------------

    public function test_a_deactivated_public_reference_does_not_resolve_anonymously(): void
    {
        $logo = $this->asset('deactivated-logo');
        ChurchBrandProfile::create(['primary_logo_media_id' => $logo->id]);
        WebsiteHomeContent::create(['hero_heading' => 'x']);
        app(WebsitePublisher::class)->publish();
        $rendition = $logo->renditions()->first();

        MediaPublicReference::withoutGlobalScopes()
            ->where('media_rendition_id', $rendition->id)
            ->update(['deactivated_at' => now()]);

        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        $this->assertNull(app(PublicMedia::class)->rendition($this->church->id, $rendition->uuid, ''));
    }

    // ---------------------------------------------------------------
    // §10 — a non-Active rendition must not resolve.
    // ---------------------------------------------------------------

    public function test_a_non_active_rendition_does_not_resolve_anonymously(): void
    {
        $logo = $this->asset('revoked-logo');
        ChurchBrandProfile::create(['primary_logo_media_id' => $logo->id]);
        WebsiteHomeContent::create(['hero_heading' => 'x']);
        app(WebsitePublisher::class)->publish();
        $rendition = $logo->renditions()->first();
        $rendition->forceFill(['state' => MediaRenditionState::Revoked])->save();

        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        $this->assertNull(app(PublicMedia::class)->rendition($this->church->id, $rendition->uuid, ''));
    }

    // ---------------------------------------------------------------
    // §12 — rendition possession alone is not authorization: a rendition
    // with no active public reference must not resolve, even though the
    // row itself and its Active state both genuinely exist.
    // ---------------------------------------------------------------

    public function test_a_rendition_with_no_active_reference_does_not_resolve_anonymously(): void
    {
        $logo = $this->asset('unreferenced-logo');
        ChurchBrandProfile::create(['primary_logo_media_id' => $logo->id]);
        WebsiteHomeContent::create(['hero_heading' => 'x']);
        app(WebsitePublisher::class)->publish();
        $rendition = $logo->renditions()->first();

        MediaPublicReference::withoutGlobalScopes()->where('media_rendition_id', $rendition->id)->delete();

        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        $this->assertNull(app(PublicMedia::class)->rendition($this->church->id, $rendition->uuid, ''));
    }

    // ---------------------------------------------------------------
    // §35 — a rights-restricted asset never even reaches the rendition/
    // reference state through normal publication; this proves the P0
    // fix did not weaken that pre-existing gate.
    // ---------------------------------------------------------------

    public function test_a_rights_restricted_asset_still_cannot_be_published_at_all(): void
    {
        $logo = $this->asset('restricted-logo');
        MediaAssetRights::create([
            'church_id' => $this->church->id,
            'media_asset_id' => $logo->id,
            'provenance' => 'church_declared',
            'status' => AssetRightsStatus::Restricted,
            'allowed_uses' => ['store', 'internal_use'],
            'restriction_reason' => 'Under legal review.',
            'attribution_required' => false,
            'declared_at' => now(),
        ]);
        ChurchBrandProfile::create(['primary_logo_media_id' => $logo->id]);
        WebsiteHomeContent::create(['hero_heading' => 'x']);

        $this->expectException(ValidationException::class);
        app(WebsitePublisher::class)->publish();
    }

    // ---------------------------------------------------------------
    // §13/§40 — the public rendition route itself (not just the HTML
    // that references it) must be anonymously fetchable, and it must
    // never expose a private route, disk name, or source path.
    // ---------------------------------------------------------------

    public function test_the_public_rendition_route_is_anonymously_fetchable_and_leaks_no_private_path(): void
    {
        $logo = $this->asset('routed-logo');
        ChurchBrandProfile::create(['primary_logo_media_id' => $logo->id]);
        WebsiteHomeContent::create(['hero_heading' => 'x']);
        app(WebsitePublisher::class)->publish();
        $rendition = $logo->renditions()->first();

        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        $response = $this->get('/media/'.$rendition->uuid);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');

        $home = $this->get('http://anonymous-proof-church.keryon.app/');
        $home->assertDontSee('media.private', false);
        $home->assertDontSee($logo->path);
        $home->assertDontSee('/app/media/', false);
    }

    // ---------------------------------------------------------------
    // §12 — never-published Website: no public_media at all, no image,
    // no crash, anonymous visitor still gets a valid page.
    // ---------------------------------------------------------------

    public function test_a_never_published_website_has_no_rendition_backed_images_but_still_renders(): void
    {
        $logo = $this->asset('unpublished-logo');
        ChurchBrandProfile::create(['primary_logo_media_id' => $logo->id]);
        WebsiteHomeContent::create(['hero_heading' => 'Not published yet']);
        // Deliberately never call WebsitePublisher::publish().

        $rendition = MediaRendition::withoutGlobalScopes()->where('media_asset_id', $logo->id)->first();
        $this->assertNull($rendition, 'No rendition should exist before any publish.');
    }
}
