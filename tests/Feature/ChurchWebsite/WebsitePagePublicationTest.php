<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Enums\WebsitePageType;
use App\Models\Church;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsitePageSetting;
use App\Models\WebsitePublication;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublicationStatus;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * K-WEB-V1-001D-B §48-§56/§82-§85 — page configuration obeys the exact
 * same publish boundary every other Website content concept already
 * does: a working-state change is invisible to the public site and does
 * not clear `pending` until an explicit, separate publish action.
 * Extends the K-WEB-V1-001B canonical fingerprint mechanism mechanically
 * — no second pending-change engine exists anywhere in this file.
 */
class WebsitePagePublicationTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        $this->church = Church::create(['name' => 'Publication Settings Church', 'slug' => 'publication-settings-church']);
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

        $response = $this->get("http://publication-settings-church.keryon.app{$path}");

        if ($authenticated !== null) {
            Auth::login($authenticated);
            app(TenantContext::class)->forgetResolved();
        }

        return $response;
    }

    // ---------------------------------------------------------------
    // §52/§56 — disabling a page: immutable until republish, then 404
    // even by direct URL, then restorable.
    // ---------------------------------------------------------------

    public function test_disabling_a_page_does_not_affect_the_live_site_until_republish(): void
    {
        app(WebsitePublisher::class)->publish();
        $this->anonymousGet('/about')->assertOk();

        WebsitePageSetting::create(['page_type' => WebsitePageType::About->value, 'enabled' => false]);

        // Still live — the publication has not changed.
        $this->anonymousGet('/about')->assertOk();
        $this->assertTrue($this->publicationStatus()['pending']);
    }

    public function test_republishing_after_disabling_a_page_removes_it_from_the_live_site(): void
    {
        app(WebsitePublisher::class)->publish();
        WebsitePageSetting::create(['page_type' => WebsitePageType::About->value, 'enabled' => false]);
        app(WebsitePublisher::class)->publish();

        $this->assertFalse($this->publicationStatus()['pending']);
        // §56 — disabled in the published snapshot: unreachable even by
        // direct URL, not merely absent from navigation.
        $this->anonymousGet('/about')->assertNotFound();
    }

    public function test_re_enabling_and_republishing_restores_public_access(): void
    {
        app(WebsitePublisher::class)->publish();
        $setting = WebsitePageSetting::create(['page_type' => WebsitePageType::About->value, 'enabled' => false]);
        app(WebsitePublisher::class)->publish();
        $this->anonymousGet('/about')->assertNotFound();

        $setting->update(['enabled' => true]);
        app(WebsitePublisher::class)->publish();

        $this->anonymousGet('/about')->assertOk();
    }

    // ---------------------------------------------------------------
    // §36/§38 — nav order / nav label immutability until republish
    // ---------------------------------------------------------------

    public function test_navigation_order_change_is_immutable_until_republish(): void
    {
        app(WebsitePublisher::class)->publish();
        $firstOrderHtml = $this->anonymousGet('/')->getContent();

        WebsitePageSetting::create(['page_type' => WebsitePageType::About->value, 'nav_order' => 1, 'enabled' => true]);

        $this->assertSame($firstOrderHtml, $this->anonymousGet('/')->getContent(), 'An unpublished nav-order change must not affect the live site.');
        $this->assertTrue($this->publicationStatus()['pending']);

        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending']);
    }

    public function test_navigation_label_change_is_immutable_until_republish(): void
    {
        app(WebsitePublisher::class)->publish();
        $this->anonymousGet('/')->assertDontSee('Our Story');

        WebsitePageSetting::create(['page_type' => WebsitePageType::About->value, 'navigation_label' => 'Our Story']);
        $this->anonymousGet('/')->assertDontSee('Our Story');
        $this->assertTrue($this->publicationStatus()['pending']);

        app(WebsitePublisher::class)->publish();
        $this->anonymousGet('/')->assertSee('Our Story');
        $this->assertFalse($this->publicationStatus()['pending']);
    }

    // ---------------------------------------------------------------
    // §53/§54 — fingerprint / pending-state contract
    // ---------------------------------------------------------------

    public function test_changing_enabled_state_reports_pending_and_republish_clears_it(): void
    {
        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending']);

        WebsitePageSetting::create(['page_type' => WebsitePageType::Ministries->value, 'enabled' => false]);
        $this->assertTrue($this->publicationStatus()['pending']);

        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending']);
    }

    public function test_changing_nav_order_reports_pending(): void
    {
        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending']);

        WebsitePageSetting::create(['page_type' => WebsitePageType::Leadership->value, 'nav_order' => 99, 'enabled' => true]);
        $this->assertTrue($this->publicationStatus()['pending']);
    }

    public function test_changing_navigation_label_reports_pending(): void
    {
        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending']);

        WebsitePageSetting::create(['page_type' => WebsitePageType::Contact->value, 'navigation_label' => 'Reach Us']);
        $this->assertTrue($this->publicationStatus()['pending']);
    }

    public function test_resaving_an_identical_effective_configuration_does_not_report_pending(): void
    {
        WebsitePageSetting::create(['page_type' => WebsitePageType::About->value, 'enabled' => true, 'nav_order' => WebsitePageType::About->defaultNavOrder()]);
        app(WebsitePublisher::class)->publish();
        $this->assertFalse($this->publicationStatus()['pending']);

        // A no-op update to the exact same effective values must not
        // cause a false-positive pending state.
        WebsitePageSetting::query()->where('page_type', WebsitePageType::About->value)->first()->update(['enabled' => true]);
        $this->assertFalse($this->publicationStatus()['pending']);
    }

    // ---------------------------------------------------------------
    // §85 — sitemap derives from published state, not working state
    // ---------------------------------------------------------------

    public function test_sitemap_reflects_the_published_state_not_the_working_state(): void
    {
        app(WebsitePublisher::class)->publish();
        $publishedSitemap = $this->anonymousGet('/sitemap.xml')->getContent();
        $this->assertStringContainsString('/about', $publishedSitemap);

        WebsitePageSetting::create(['page_type' => WebsitePageType::About->value, 'enabled' => false]);

        $stillPublished = $this->anonymousGet('/sitemap.xml')->getContent();
        $this->assertStringContainsString('/about', $stillPublished, 'The live sitemap must not change until republished.');

        app(WebsitePublisher::class)->publish();
        $updatedSitemap = $this->anonymousGet('/sitemap.xml')->getContent();
        $this->assertStringNotContainsString('/about', $updatedSitemap);
    }

    // ---------------------------------------------------------------
    // §30/§50/§51 — historical publication snapshot never rewritten
    // ---------------------------------------------------------------

    public function test_historical_publication_page_settings_are_never_rewritten(): void
    {
        $first = app(WebsitePublisher::class)->publish();
        $firstSnapshotSettings = $first->snapshot['page_settings'];

        WebsitePageSetting::create(['page_type' => WebsitePageType::About->value, 'enabled' => false]);
        app(WebsitePublisher::class)->publish();

        $this->assertSame(
            $firstSnapshotSettings,
            $first->fresh()->snapshot['page_settings'],
            'A historical publication\'s own page-settings snapshot must never be rewritten.',
        );
    }

    /**
     * K-WEB-V1-001D-B §50/§51 — a publication made before this milestone
     * existed (no `page_settings` key at all in its stored snapshot) must
     * still render exactly as it always did — legacy defaults resolve
     * identically to a Church with zero `website_page_settings` rows.
     */
    public function test_a_publication_predating_this_milestone_still_renders_every_page(): void
    {
        $publication = app(WebsitePublisher::class)->publish();
        $legacySnapshot = $publication->snapshot;
        unset($legacySnapshot['page_settings']);

        // `WebsitePublication` is deliberately immutable (`updating`
        // throws) — a raw DB update is the established, event-bypassing
        // pattern this codebase already uses to fabricate a historical
        // shape for a backward-compatibility test (see
        // `WebsitePublicationFingerprintCorrectnessTest`'s equivalent
        // direct `DB::table()` writes).
        DB::table('website_publications')
            ->where('id', $publication->id)
            ->update(['snapshot' => json_encode($legacySnapshot, JSON_THROW_ON_ERROR)]);

        foreach (['/', '/about', '/leadership', '/ministries', '/contact'] as $path) {
            $this->anonymousGet($path)->assertOk();
        }
    }
}
