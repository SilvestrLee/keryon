<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Enums\WebsitePageType;
use App\Models\Church;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteLeadershipProfile;
use App\Models\WebsitePublication;
use App\Models\WebsiteSettings;
use App\PublicWebsite\Themes\Proclaim\ProclaimTheme;
use App\PublicWebsite\Themes\ThemeRegistry;
use App\PublicWebsite\Themes\ThemeRenderer;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * K-WEB-V1-001D-B §77/§86 — proves theme support is a real, consulted
 * contract, not just an unused interface method. `LimitedTestTheme`
 * below is a bounded test double declaring support for a *smaller*
 * subset than Proclaim (Home + Contact only) while delegating actual
 * rendering to the real `ProclaimTheme` — it is never registered as a
 * production theme, only bound into the container for the lifetime of
 * a single test, per the directive's explicit "no fake production
 * theme" instruction.
 */
class ThemePageSupportTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        $this->church = Church::create(['name' => 'Theme Support Church', 'slug' => 'theme-support-church']);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        WebsiteSettings::create(['theme' => 'proclaim']);
        WebsiteHomeContent::create(['hero_heading' => 'Welcome']);
    }

    /**
     * K-WEB-V1-001D-C §103 — updated from K-WEB-V1-001D-B's "exactly the
     * five" expectation now that Events/Messages/Publications/Giving
     * have real templates/renderers too.
     */
    public function test_proclaim_declares_exactly_the_nine_currently_implemented_page_types(): void
    {
        $supported = app(ProclaimTheme::class)->supportedPageTypes();

        $this->assertEqualsCanonicalizing(
            ['home', 'about', 'leadership', 'ministries', 'events', 'messages', 'publications', 'giving', 'contact'],
            array_map(fn (WebsitePageType $type): string => $type->value, $supported),
        );
    }

    public function test_a_page_type_unsupported_by_the_active_theme_cannot_render_publicly(): void
    {
        app(WebsitePublisher::class)->publish();
        $this->bindLimitedTheme();

        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        // Home (supported by the limited theme) still renders.
        $this->get('http://theme-support-church.keryon.app/')->assertOk();
        // Leadership (not declared supported) is publicly unreachable —
        // never a generic CMS fallback, a clean 404.
        $this->get('http://theme-support-church.keryon.app/leadership')->assertNotFound();
    }

    public function test_a_page_type_unsupported_by_the_active_theme_cannot_be_previewed(): void
    {
        $this->bindLimitedTheme();

        $this->get(route('website.preview'))->assertOk();
        $this->get(route('website.preview', ['page' => 'leadership']))->assertNotFound();
    }

    /**
     * K-WEB-V1-001D-B §9/§46/§86 — half of the theme-switch user story:
     * content stored under a page type the active theme doesn't support
     * remains completely intact (never deleted, never mutated) and
     * becomes publicly unavailable while unsupported. The other half —
     * that support being restored makes it available again — is proven
     * separately in `test_a_page_type_supported_by_the_theme_is_publicly_available`
     * below (Proclaim, the real registered theme, already supports all
     * five current types; deliberately kept as a second, fresh test
     * rather than reusing this one's app/container, since Laravel caches
     * a resolved controller instance *per route object*, and a single
     * test reusing the same route after rebinding the container would
     * not exercise a genuinely fresh resolution — an artifact of the
     * test harness, not of production, where every request is a fresh
     * process).
     */
    public function test_a_theme_that_stops_supporting_a_page_type_hides_it_without_deleting_content(): void
    {
        WebsiteLeadershipProfile::create([
            'name' => 'Pastor Preserved', 'category' => 'pastor', 'role_title' => 'Lead Pastor', 'sort_order' => 1,
        ]);
        app(WebsitePublisher::class)->publish();

        // Switch to the limited theme (Leadership unsupported) — no
        // republish needed, since theme support is consulted at *render*
        // time against the already-published theme string, exactly like
        // a real theme rollout would be.
        $this->bindLimitedTheme();
        Auth::logout();
        app(TenantContext::class)->forgetResolved();
        $this->get('http://theme-support-church.keryon.app/leadership')->assertNotFound();
        $this->assertSame(
            1,
            WebsiteLeadershipProfile::withoutGlobalScope('church_tenant')->where('church_id', $this->church->id)->count(),
            'Content must never be deleted merely because the active theme stops supporting its page type.',
        );
    }

    /**
     * K-WEB-V1-001D-B §86 — the other half: a page type the active
     * theme *does* support (Proclaim's real, unmodified registration)
     * renders the Church's own content normally — completing the
     * theme-switch story alongside the test above.
     */
    public function test_a_page_type_supported_by_the_theme_is_publicly_available(): void
    {
        WebsiteLeadershipProfile::create([
            'name' => 'Pastor Preserved', 'category' => 'pastor', 'role_title' => 'Lead Pastor', 'sort_order' => 1,
        ]);
        app(WebsitePublisher::class)->publish();

        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        $this->get('http://theme-support-church.keryon.app/leadership')
            ->assertOk()
            ->assertSee('Pastor Preserved');
    }

    private function bindLimitedTheme(): void
    {
        $this->app->forgetInstance(ThemeRegistry::class);
        $this->app->bind(ThemeRegistry::class, function ($app): ThemeRegistry {
            return new class($app->make(ProclaimTheme::class)) extends ThemeRegistry
            {
                public function __construct(private readonly ProclaimTheme $inner) {}

                public function resolve(string $theme): ?ThemeRenderer
                {
                    return $theme === 'proclaim' ? new LimitedTestTheme($this->inner) : null;
                }
            };
        });
    }
}

/**
 * K-WEB-V1-001D-B §77/§86 — a bounded test-only theme double. Never
 * registered in `ThemeRegistry`'s real mapping, never reachable in
 * production — exists only to prove the theme-support contract is
 * actually consulted by route/preview dispatch. Delegates all real
 * rendering to the genuine `ProclaimTheme` so a "supported" page still
 * renders real content, not a stub.
 */
final class LimitedTestTheme implements ThemeRenderer
{
    public function __construct(private readonly ProclaimTheme $inner) {}

    public function renderWorking(string $page, Church $church, bool $preview = false): View
    {
        return $this->inner->renderWorking($page, $church, $preview);
    }

    public function renderPublished(string $page, WebsitePublication $publication): View
    {
        return $this->inner->renderPublished($page, $publication);
    }

    public function supportedPageTypes(): array
    {
        return [WebsitePageType::Home, WebsitePageType::Contact];
    }
}
