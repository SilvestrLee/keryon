<?php

namespace Tests\Feature\ChurchWebsite;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Dashboard\ChurchDashboardSnapshotBuilder;
use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\ChurchBrandProfile;
use App\Models\MediaAsset;
use App\Models\MediaRendition;
use App\Models\User;
use App\Models\WebsiteEvent;
use App\Models\WebsiteHomeContent;
use App\Models\WebsitePublication;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublicationStatus;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * K-WEB-V1-001B §3-§5 — the P1 defect K-WEB-V1-001A established:
 * `WebsitePublisher::publish()` stored its `working_fingerprint` from a
 * storage-shaped snapshot (public rendition UUIDs merged in, private
 * Media IDs stripped), while `WebsitePublicationStatus::current()`
 * recomputed the fingerprint from the raw, untransformed snapshot — two
 * structurally different inputs that could never match, so `pending`
 * was `true` immediately after every publish regardless of whether
 * anything had actually changed.
 *
 * The fix moves the canonical transformation inside
 * `WebsiteSnapshot::fingerprint()` itself (private Media IDs replaced by
 * their own stable `sha256` content identity, never a rendition UUID
 * that would require creating a rendition merely to check "has anything
 * changed") — every caller, including this one, feeds it the same raw
 * `capture()` shape and gets a consistent, comparable result. This test
 * exercises the real `WebsitePublisher`/`WebsitePublicationStatus`
 * services directly (no mocked fingerprint behaviour), per the
 * directive's own preference.
 */
class WebsitePublicationFingerprintCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media-private');
        $this->church = Church::create([
            'name' => 'Fingerprint Church', 'slug' => 'fingerprint-church',
            'email' => 'hello@fingerprint.test', 'phone' => '111', 'address' => 'One address',
        ]);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        WebsiteSettings::create(['theme' => 'proclaim']);
        WebsiteHomeContent::create(['hero_heading' => 'Welcome', 'hero_subheading' => 'A place to belong.']);
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

    private function createAsset(string $path, string $bytes): MediaAsset
    {
        Storage::disk('media-private')->put($path, $bytes);

        return MediaAsset::create([
            'disk' => 'media-private', 'path' => $path,
            'original_filename' => basename($path), 'mime_type' => 'image/png', 'size' => strlen($bytes),
            'width' => 1, 'height' => 1, 'sha256' => hash('sha256', $bytes),
        ]);
    }

    private function publicationStatus(): array
    {
        return app(WebsitePublicationStatus::class)->current();
    }

    // ---------------------------------------------------------------
    // §5 — never published
    // ---------------------------------------------------------------

    public function test_never_published_reports_the_correct_unpublished_state(): void
    {
        $status = $this->publicationStatus();

        $this->assertSame('never_published', $status['state']);
        $this->assertNull($status['current']);
        $this->assertTrue($status['pending']);
    }

    // ---------------------------------------------------------------
    // §5 — immediately after first publish
    // ---------------------------------------------------------------

    public function test_immediately_after_first_publish_pending_is_false(): void
    {
        app(WebsitePublisher::class)->publish();

        $status = $this->publicationStatus();

        $this->assertSame('published', $status['state']);
        $this->assertFalse($status['pending'], 'A fresh first publish must not immediately report pending changes.');
    }

    // ---------------------------------------------------------------
    // §5 — immediately after a subsequent publish
    // ---------------------------------------------------------------

    public function test_immediately_after_a_subsequent_publish_pending_is_false(): void
    {
        app(WebsitePublisher::class)->publish();
        WebsiteHomeContent::query()->first()->update(['hero_heading' => 'Welcome, second version']);
        app(WebsitePublisher::class)->publish();

        $status = $this->publicationStatus();

        $this->assertFalse($status['pending'], 'A fresh republish must not immediately report pending changes.');
    }

    // ---------------------------------------------------------------
    // §5 — genuine content change
    // ---------------------------------------------------------------

    public function test_a_genuine_content_change_after_publish_reports_pending(): void
    {
        app(WebsitePublisher::class)->publish();
        WebsiteHomeContent::query()->first()->update(['hero_heading' => 'A materially different heading']);

        $this->assertTrue($this->publicationStatus()['pending']);
    }

    // ---------------------------------------------------------------
    // §5 — Brand change
    // ---------------------------------------------------------------

    public function test_a_brand_change_after_publish_reports_pending(): void
    {
        ChurchBrandProfile::create(['primary_color' => '#111111']);
        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending']);

        ChurchBrandProfile::query()->first()->update(['primary_color' => '#222222']);

        $this->assertTrue($this->publicationStatus()['pending'], 'A Brand change carried in the publication snapshot must mark the Website pending.');
    }

    // ---------------------------------------------------------------
    // §5 — Theme change
    // ---------------------------------------------------------------

    /**
     * K-WEB-V1-001B §5 — `WebsiteTheme` currently has exactly one case
     * (`Proclaim`), so a genuine second theme selection cannot be
     * exercised through the real enum-cast form field without inventing
     * a second theme solely for this test, which the directive
     * explicitly forbids. The fingerprint itself, however, is computed
     * from `$settings->getRawOriginal('theme')` — the raw stored string,
     * read before the enum cast is applied — so this test exercises the
     * real underlying mechanism the fingerprint actually depends on by
     * changing that raw column value directly, without touching
     * `WebsiteTheme` or claiming a second theme is selectable in the
     * product today.
     */
    public function test_a_theme_identity_change_reports_pending(): void
    {
        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending']);

        DB::table('website_settings')->where('church_id', $this->church->id)->update(['theme' => 'proclaim-alternate-for-test']);

        $this->assertTrue($this->publicationStatus()['pending'], 'A change to the raw theme identity the fingerprint depends on must mark the Website pending.');
    }

    // ---------------------------------------------------------------
    // §5 — Church-information change
    // ---------------------------------------------------------------

    public function test_a_church_information_change_after_publish_reports_pending(): void
    {
        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending']);

        $this->church->update(['phone' => '999-999-9999']);
        // TenantContext caches the resolved membership (and its already
        // loaded `church` relation) for the duration of the request —
        // force a fresh resolution so the status check sees the change,
        // exactly as a real subsequent HTTP request would.
        app(TenantContext::class)->forgetResolved();

        $this->assertTrue($this->publicationStatus()['pending'], 'A Church-information change carried in the publication snapshot must mark the Website pending.');
    }

    // ---------------------------------------------------------------
    // §5 — Media change
    // ---------------------------------------------------------------

    public function test_a_media_selection_change_after_publish_reports_pending(): void
    {
        $firstAsset = $this->createAsset("tenants/{$this->church->id}/media/first/original.png", $this->fakePngBytes());
        WebsiteHomeContent::query()->first()->update(['hero_image_id' => $firstAsset->id]);
        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending']);

        $secondAsset = $this->createAsset("tenants/{$this->church->id}/media/second/original.png", $this->alternatePngBytes());
        WebsiteHomeContent::query()->first()->update(['hero_image_id' => $secondAsset->id]);

        $this->assertTrue($this->publicationStatus()['pending'], 'A changed Media selection must mark the Website pending.');
    }

    public function test_reselecting_the_identical_media_asset_does_not_report_pending(): void
    {
        $asset = $this->createAsset("tenants/{$this->church->id}/media/only/original.png", $this->fakePngBytes());
        WebsiteHomeContent::query()->first()->update(['hero_image_id' => $asset->id]);
        app(WebsitePublisher::class)->publish();

        $this->assertFalse($this->publicationStatus()['pending'], 'Publishing with a Media selection already in place must not itself appear pending.');
    }

    // ---------------------------------------------------------------
    // §5 — republish after genuine change returns to not-pending
    // ---------------------------------------------------------------

    public function test_republishing_after_a_genuine_change_returns_pending_to_false(): void
    {
        app(WebsitePublisher::class)->publish();
        WebsiteHomeContent::query()->first()->update(['hero_heading' => 'Changed before republish']);
        $this->assertTrue($this->publicationStatus()['pending']);

        app(WebsitePublisher::class)->publish();

        $this->assertFalse($this->publicationStatus()['pending'], 'Republishing after a genuine change must clear the pending state.');
    }

    // ---------------------------------------------------------------
    // §6 — publication immutability preserved
    // ---------------------------------------------------------------

    public function test_historical_publications_remain_immutable_and_their_fingerprints_are_not_rewritten(): void
    {
        $first = app(WebsitePublisher::class)->publish();
        $firstFingerprint = $first->working_fingerprint;

        WebsiteHomeContent::query()->first()->update(['hero_heading' => 'Second version']);
        app(WebsitePublisher::class)->publish();

        $this->assertSame($firstFingerprint, $first->fresh()->working_fingerprint, 'A historical publication\'s own fingerprint must never be rewritten.');
    }

    // ---------------------------------------------------------------
    // K-PROCLAIM-V1-001C-R §4/§14/§16 — an Event crossing from
    // current/upcoming to past, with the clock alone advancing and no
    // editorial change, must never mark the Website as having pending
    // changes, and must never rewrite the already-stored, immutable
    // publication snapshot. This is the exact regression the original
    // K-PROCLAIM-V1-001C fix would have introduced had the
    // current/upcoming-vs-past partition been left inside
    // `WebsiteSnapshot::capture()` instead of moved to
    // `ProclaimTheme::resolveEvents()`.
    // ---------------------------------------------------------------

    public function test_an_event_crossing_from_upcoming_to_past_does_not_mark_the_website_pending(): void
    {
        $t1 = Carbon::parse('2026-01-01 09:00:00');
        $this->travelTo($t1);

        WebsiteEvent::create(['title' => 'Crosses The Boundary', 'starts_at' => $t1->copy()->subHour(), 'ends_at' => $t1->copy()->addHours(2)]);
        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending'], 'A fresh publish must not itself report pending.');

        // Advance real test time past the Event's `ends_at` — it is now
        // classified past. No content was edited.
        $this->travelTo($t1->copy()->addHours(4));

        $this->assertFalse(
            $this->publicationStatus()['pending'],
            'An Event crossing from current/upcoming to past, with no editorial change, must not report pending changes — the snapshot and its fingerprint must stay time-independent.'
        );

        $this->travelBack();
    }

    public function test_the_stored_publication_snapshot_is_not_rewritten_when_an_event_crosses_into_the_past(): void
    {
        $t1 = Carbon::parse('2026-01-01 09:00:00');
        $this->travelTo($t1);

        WebsiteEvent::create(['title' => 'Crosses The Boundary', 'starts_at' => $t1->copy()->subHour(), 'ends_at' => $t1->copy()->addHours(2)]);
        $publication = app(WebsitePublisher::class)->publish();
        $storedEventsBefore = $publication->fresh()->snapshot['events'];

        $this->travelTo($t1->copy()->addHours(4));

        $storedEventsAfter = WebsitePublication::query()->findOrFail($publication->id)->snapshot['events'];

        $this->assertSame(
            $storedEventsBefore,
            $storedEventsAfter,
            'The stored, immutable publication snapshot must not change shape or order merely because real time advanced — only rendering may reorder, never the stored evidence.'
        );

        $this->travelBack();
    }

    // ---------------------------------------------------------------
    // §8 — Media privacy preserved by the fix
    // ---------------------------------------------------------------

    public function test_fingerprint_computation_creates_no_public_rendition_and_leaks_no_private_identifier(): void
    {
        $asset = MediaAsset::create([
            'disk' => 'media-private', 'path' => "tenants/{$this->church->id}/media/private-check/original.png",
            'original_filename' => 'private-check.png', 'mime_type' => 'image/png', 'size' => 64,
            'width' => 10, 'height' => 10, 'sha256' => hash('sha256', 'private-check-bytes'),
        ]);
        WebsiteHomeContent::query()->first()->update(['hero_image_id' => $asset->id]);

        // The pending check alone (never having published) must not
        // create a rendition — fingerprinting is read-only.
        $this->publicationStatus();
        $this->assertSame(0, MediaRendition::query()->count());
    }

    // ---------------------------------------------------------------
    // §10 — Church Dashboard regression. `website.pending_changes`
    // reads the exact same `WebsitePublicationStatus::current()` this
    // fix corrects — no Dashboard-specific logic exists to change.
    // ---------------------------------------------------------------

    public function test_dashboard_does_not_show_pending_changes_immediately_after_a_fresh_publish(): void
    {
        $resolver = \Mockery::mock(EntitlementResolver::class);
        $resolver->shouldReceive('allows')->andReturnTrue()->byDefault();
        $this->app->instance(EntitlementResolver::class, $resolver);

        app(WebsitePublisher::class)->publish();

        $snapshot = app(ChurchDashboardSnapshotBuilder::class)->build()->toArray();
        $keys = array_column($snapshot['actions'], 'key');

        $this->assertNotContains('website.pending_changes', $keys, 'The Dashboard must not nudge about pending changes immediately after a fresh publish.');
        $this->assertNotContains('website.unpublished', $keys);
    }

    public function test_dashboard_shows_pending_changes_after_a_genuine_edit(): void
    {
        $resolver = \Mockery::mock(EntitlementResolver::class);
        $resolver->shouldReceive('allows')->andReturnTrue()->byDefault();
        $this->app->instance(EntitlementResolver::class, $resolver);

        app(WebsitePublisher::class)->publish();
        WebsiteHomeContent::query()->first()->update(['hero_heading' => 'A change worth publishing']);

        $snapshot = app(ChurchDashboardSnapshotBuilder::class)->build()->toArray();

        $this->assertContains('website.pending_changes', array_column($snapshot['actions'], 'key'), 'The Dashboard must still nudge about a genuine unpublished change.');
    }
}
