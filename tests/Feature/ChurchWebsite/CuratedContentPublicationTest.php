<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Enums\WebsitePageType;
use App\Models\Church;
use App\Models\ChurchPublication;
use App\Models\User;
use App\Models\WebsiteEvent;
use App\Models\WebsiteGivingContent;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteMessage;
use App\Models\WebsitePageSetting;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublicationStatus;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * K-WEB-V1-001D-C §111-121 — cross-cutting publication-boundary
 * coverage for the four new curated page types: the enablement matrix,
 * routing, navigation, sitemap, preview, fingerprint, historical
 * immutability, old-snapshot compatibility, the anonymous-public
 * contract, and external-URL security. Mechanically mirrors
 * `WebsitePagePublicationTest` (K-WEB-V1-001D-B) — no second publish
 * engine, no second fingerprint mechanism exists here.
 */
class CuratedContentPublicationTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        $this->church = Church::create(['name' => 'Curated Content Church', 'slug' => 'curated-content-church']);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        WebsiteSettings::create(['theme' => 'proclaim']);
        WebsiteHomeContent::create(['hero_heading' => 'Welcome']);
    }

    private function publicationStatus(): array
    {
        return app(WebsitePublicationStatus::class)->current();
    }

    private function anonymousGet(string $path = '/')
    {
        $authenticated = Auth::user();
        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        $response = $this->get("http://curated-content-church.keryon.app{$path}");

        if ($authenticated !== null) {
            Auth::login($authenticated);
            app(TenantContext::class)->forgetResolved();
        }

        return $response;
    }

    private function enable(WebsitePageType $type): void
    {
        WebsitePageSetting::create(['page_type' => $type->value, 'enabled' => true]);
    }

    // ---------------------------------------------------------------
    // §10/§102/§111 — enablement matrix: each of the 4 new pages
    // defaults to disabled (404), stays 404 with content present but
    // not yet enabled, still 404 while enabled-but-unpublished, and
    // only becomes 200 once enabled *and* published.
    // ---------------------------------------------------------------

    public static function newPageProvider(): array
    {
        return [
            'events' => ['/events', WebsitePageType::Events],
            'messages' => ['/messages', WebsitePageType::Messages],
            'publications' => ['/publications', WebsitePageType::Publications],
            'giving' => ['/giving', WebsitePageType::Giving],
        ];
    }

    #[DataProvider('newPageProvider')]
    public function test_a_new_page_type_is_never_publicly_reachable_by_default(string $path, WebsitePageType $type): void
    {
        app(WebsitePublisher::class)->publish();

        $this->anonymousGet($path)->assertNotFound();
    }

    #[DataProvider('newPageProvider')]
    public function test_a_new_page_type_stays_unreachable_while_enabled_but_unpublished(string $path, WebsitePageType $type): void
    {
        app(WebsitePublisher::class)->publish();
        $this->enable($type);

        // Enabling is a working-state change — invisible until republish,
        // exactly like every other page-setting change (§111).
        $this->anonymousGet($path)->assertNotFound();
        $this->assertTrue($this->publicationStatus()['pending']);
    }

    #[DataProvider('newPageProvider')]
    public function test_a_new_page_type_becomes_reachable_once_enabled_and_published(string $path, WebsitePageType $type): void
    {
        $this->enable($type);
        app(WebsitePublisher::class)->publish();

        $this->anonymousGet($path)->assertOk();
        $this->assertFalse($this->publicationStatus()['pending']);
    }

    #[DataProvider('newPageProvider')]
    public function test_a_new_page_type_returns_to_404_once_disabled_and_republished(string $path, WebsitePageType $type): void
    {
        $setting = WebsitePageSetting::create(['page_type' => $type->value, 'enabled' => true]);
        app(WebsitePublisher::class)->publish();
        $this->anonymousGet($path)->assertOk();

        $setting->update(['enabled' => false]);
        app(WebsitePublisher::class)->publish();

        $this->anonymousGet($path)->assertNotFound();
    }

    // ---------------------------------------------------------------
    // §112/§113 — no detail routes exist for any of the four types
    // ---------------------------------------------------------------

    public function test_no_detail_route_exists_for_any_new_page_type(): void
    {
        $event = WebsiteEvent::create(['title' => 'Detail Test', 'starts_at' => now()->addWeek()]);
        $message = WebsiteMessage::create(['title' => 'Detail Test']);
        $publication = ChurchPublication::create(['title' => 'Detail Test']);

        foreach ([WebsitePageType::Events, WebsitePageType::Messages, WebsitePageType::Publications] as $type) {
            $this->enable($type);
        }
        app(WebsitePublisher::class)->publish();

        $this->anonymousGet("/events/{$event->id}")->assertNotFound();
        $this->anonymousGet("/messages/{$message->id}")->assertNotFound();
        $this->anonymousGet("/publications/{$publication->id}")->assertNotFound();
    }

    // ---------------------------------------------------------------
    // §114 — navigation integration: label/order resolve for the 4
    // new types exactly like the legacy five, only once enabled.
    // ---------------------------------------------------------------

    public function test_an_enabled_new_page_appears_in_navigation_with_its_default_label_and_order(): void
    {
        $this->enable(WebsitePageType::Events);
        app(WebsitePublisher::class)->publish();

        $home = $this->anonymousGet('/')->getContent();
        $this->assertStringContainsString('Events', $home);
    }

    public function test_a_disabled_new_page_never_appears_in_navigation(): void
    {
        app(WebsitePublisher::class)->publish();

        $this->anonymousGet('/')->assertDontSee('Events')->assertDontSee('Messages')
            ->assertDontSee('Publications')->assertDontSee('Giving');
    }

    public function test_a_custom_navigation_label_for_a_new_page_resolves_once_published(): void
    {
        WebsitePageSetting::create(['page_type' => WebsitePageType::Messages->value, 'enabled' => true, 'navigation_label' => 'Sermons']);
        app(WebsitePublisher::class)->publish();

        $this->anonymousGet('/')->assertSee('Sermons');
    }

    // ---------------------------------------------------------------
    // §115 — sitemap only ever lists a new page once enabled+published
    // ---------------------------------------------------------------

    public function test_sitemap_only_lists_a_new_page_once_enabled_and_published(): void
    {
        app(WebsitePublisher::class)->publish();
        $this->assertStringNotContainsString('/giving', $this->anonymousGet('/sitemap.xml')->getContent());

        $this->enable(WebsitePageType::Giving);
        app(WebsitePublisher::class)->publish();
        $this->assertStringContainsString('/giving', $this->anonymousGet('/sitemap.xml')->getContent());
    }

    // ---------------------------------------------------------------
    // §116 — preview: reachable regardless of enabled state (matching
    // the deliberate, pre-existing preview behavior), never indexable.
    // ---------------------------------------------------------------

    public function test_a_new_page_is_previewable_even_while_disabled_and_marked_noindex(): void
    {
        $response = $this->get(route('website.preview', ['page' => 'events']));

        $response->assertOk();
        $response->assertSee('noindex, nofollow', false);
    }

    public function test_every_new_page_type_is_previewable(): void
    {
        foreach (['events', 'messages', 'publications', 'giving'] as $page) {
            $this->get(route('website.preview', ['page' => $page]))->assertOk();
        }
    }

    // ---------------------------------------------------------------
    // §117 — fingerprint: a representative content edit reports pending
    // and republishing clears it, for each of the four domains.
    // ---------------------------------------------------------------

    public function test_editing_event_content_reports_pending_and_republish_clears_it(): void
    {
        $this->enable(WebsitePageType::Events);
        $event = WebsiteEvent::create(['title' => 'Original', 'starts_at' => now()->addWeek()]);
        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending']);

        $event->update(['title' => 'Changed']);
        $this->assertTrue($this->publicationStatus()['pending']);

        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending']);
    }

    public function test_editing_giving_content_reports_pending_and_republish_clears_it(): void
    {
        $this->enable(WebsitePageType::Giving);
        $giving = WebsiteGivingContent::create(['headline' => 'Original']);
        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending']);

        $giving->update(['headline' => 'Changed']);
        $this->assertTrue($this->publicationStatus()['pending']);

        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending']);
    }

    // ---------------------------------------------------------------
    // §118 — historical publication immutability
    // ---------------------------------------------------------------

    public function test_a_historical_publications_event_and_giving_snapshot_is_never_rewritten(): void
    {
        $this->enable(WebsitePageType::Events);
        $this->enable(WebsitePageType::Giving);
        WebsiteEvent::create(['title' => 'Historical Event', 'starts_at' => now()->addWeek()]);
        WebsiteGivingContent::create(['headline' => 'Historical Giving']);

        $first = app(WebsitePublisher::class)->publish();
        $firstEvents = $first->snapshot['events'];
        $firstGiving = $first->snapshot['giving'];

        WebsiteEvent::first()->update(['title' => 'Changed After Publish']);
        WebsiteGivingContent::first()->update(['headline' => 'Changed After Publish']);
        app(WebsitePublisher::class)->publish();

        $this->assertSame($firstEvents, $first->fresh()->snapshot['events']);
        $this->assertSame($firstGiving, $first->fresh()->snapshot['giving']);
    }

    // ---------------------------------------------------------------
    // §119 — a publication predating K-WEB-V1-001D-C (no
    // events/messages/publications/giving keys at all in its stored
    // snapshot) must still render every legacy page without error.
    // ---------------------------------------------------------------

    public function test_a_publication_predating_this_milestone_still_renders_safely(): void
    {
        $publication = app(WebsitePublisher::class)->publish();
        $legacySnapshot = $publication->snapshot;
        unset($legacySnapshot['events'], $legacySnapshot['messages'], $legacySnapshot['publications'], $legacySnapshot['giving']);

        DB::table('website_publications')
            ->where('id', $publication->id)
            ->update(['snapshot' => json_encode($legacySnapshot, JSON_THROW_ON_ERROR)]);

        foreach (['/', '/about', '/leadership', '/ministries', '/contact'] as $path) {
            $this->anonymousGet($path)->assertOk();
        }

        // The four new pages remain unreachable exactly as they would
        // for any Church that has never configured them — no crash from
        // the missing snapshot keys.
        foreach (['/events', '/messages', '/publications', '/giving'] as $path) {
            $this->anonymousGet($path)->assertNotFound();
        }
    }

    // ---------------------------------------------------------------
    // §120 — anonymous-public contract: no admin session leaks into a
    // "public" request used for verification.
    // ---------------------------------------------------------------

    public function test_public_event_page_is_reachable_with_no_authenticated_session_at_all(): void
    {
        $this->enable(WebsitePageType::Events);
        WebsiteEvent::create(['title' => 'Anonymous Visible Event', 'starts_at' => now()->addWeek()]);
        app(WebsitePublisher::class)->publish();

        Auth::logout();
        app(TenantContext::class)->forgetResolved();
        $this->assertNull(Auth::user());

        $this->get('http://curated-content-church.keryon.app/events')
            ->assertOk()
            ->assertSee('Anonymous Visible Event');
    }

    // ---------------------------------------------------------------
    // §121 — external-URL security: no javascript:/data: URL is ever
    // rendered on any of the four new public pages.
    // ---------------------------------------------------------------

    public function test_unsafe_url_protocols_never_reach_the_public_event_page(): void
    {
        $this->enable(WebsitePageType::Events);
        $event = WebsiteEvent::create(['title' => 'Unsafe CTA Event', 'starts_at' => now()->addWeek()]);
        // Bypass form validation via a direct, forceful DB write —
        // proving the *rendering* layer's own defense, not just the
        // Filament form's client-side validation.
        DB::table('website_events')->where('id', $event->id)->update(['cta_url' => 'javascript:alert(1)']);
        app(WebsitePublisher::class)->publish();

        $html = $this->anonymousGet('/events')->getContent();
        $this->assertStringNotContainsString('javascript:alert(1)', $html);
    }

    public function test_unsafe_url_protocols_never_reach_the_public_giving_page(): void
    {
        $this->enable(WebsitePageType::Giving);
        $giving = WebsiteGivingContent::create(['headline' => 'Unsafe Giving']);
        DB::table('website_giving_contents')->where('id', $giving->id)->update(['giving_url' => 'data:text/html,<script>alert(1)</script>']);
        app(WebsitePublisher::class)->publish();

        $html = $this->anonymousGet('/giving')->getContent();
        $this->assertStringNotContainsString('data:text/html', $html);
    }

    public function test_unsafe_url_protocols_never_reach_the_public_publications_page(): void
    {
        $this->enable(WebsitePageType::Publications);
        $publication = ChurchPublication::create(['title' => 'Unsafe Purchase Book']);
        DB::table('church_publications')->where('id', $publication->id)->update(['purchase_url' => 'javascript:alert(1)']);
        app(WebsitePublisher::class)->publish();

        $html = $this->anonymousGet('/publications')->getContent();
        $this->assertStringNotContainsString('javascript:alert(1)', $html);
    }

    public function test_unsafe_url_protocols_never_reach_the_public_messages_page(): void
    {
        $this->enable(WebsitePageType::Messages);
        $message = WebsiteMessage::create(['title' => 'Unsafe Media Message']);
        DB::table('website_messages')->where('id', $message->id)->update(['media_url' => 'javascript:alert(1)']);
        app(WebsitePublisher::class)->publish();

        $html = $this->anonymousGet('/messages')->getContent();
        $this->assertStringNotContainsString('javascript:alert(1)', $html);
    }
}
