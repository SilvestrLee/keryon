<?php

namespace Tests\Feature\Domains;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Domain\ChurchDomainLifecycle;
use App\Domain\MakeChurchDomainPrimary;
use App\Domain\RequestChurchCustomDomain;
use App\Enums\ChurchRole;
use App\Http\Middleware\RejectUnsupportedKeryonHost;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * K-WEB-P2-ROBOTS-001B — owns the platform-wide robots authority matrix:
 * the static `public/robots.txt` scaffold that used to shadow every one
 * of these hosts before Laravel's router ever ran (K-WEB-P2-ROBOTS-001A)
 * has been removed, and each Keryon host class now declares its own
 * intentional crawler policy. This class asserts that matrix; it does
 * not re-derive `PublicWebsiteController::robots()`/`sitemap()`'s own
 * correctness, which `CustomDomainRoutingTest` already covers.
 */
class RobotsAuthorityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $entitlements = Mockery::mock(EntitlementResolver::class);
        $entitlements->shouldReceive('allows')->andReturnTrue();
        $this->app->instance(EntitlementResolver::class, $entitlements);
    }

    public function test_the_static_scaffold_file_never_returns(): void
    {
        $this->assertFileDoesNotExist(base_path('public/robots.txt'), 'public/robots.txt must remain permanently absent — see K-WEB-P2-ROBOTS-001A.');
    }

    public function test_marketing_host_allows_everything_and_declares_no_sitemap_yet(): void
    {
        $response = $this->get('http://keryon.app/robots.txt');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertContent("User-agent: *\nAllow: /\n");
        $this->assertStringNotContainsString('Sitemap:', $response->getContent());
    }

    public function test_www_marketing_alias_redirects_to_canonical_robots(): void
    {
        $this->get('http://www.keryon.app/robots.txt')
            ->assertStatus(308)
            ->assertRedirect('https://keryon.app/robots.txt');
    }

    public function test_application_workspace_host_disallows_everything(): void
    {
        $response = $this->get('http://app.keryon.app/robots.txt');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertContent("User-agent: *\nDisallow: /\n");
        $this->assertStringNotContainsString('Sitemap:', $response->getContent());
        $this->assertStringNotContainsString('Allow: /', $response->getContent());
    }

    /**
     * `central.domain` is env-driven with no literal default (see
     * `config/central.php`) and is unset in this application's test
     * environment — exactly the same constraint `PlatformMfaSecurityTest`
     * already works around by exercising the Central middleware directly
     * rather than through a real domain-bound route. A live route can
     * only be registered for a domain the application actually knows
     * about at boot, so this test instead proves the registration guard
     * itself is correct in both directions: no host, no route (proven
     * here); a host present, the equivalent route registered before the
     * Church wildcard (proven out-of-process via `route:list` and a real
     * HTTP request with `CENTRAL_DOMAIN` set, both recorded in the
     * K-WEB-P2-ROBOTS-001B implementation report, per its own §25
     * instruction that PHPUnit alone cannot stand in for real HTTP/host
     * verification).
     */
    public function test_central_robots_route_registration_is_guarded_by_configured_domain(): void
    {
        $this->assertSame('', (string) config('central.domain'), 'This assertion documents the test-environment precondition the rest of this test relies on.');

        $registered = collect(Route::getRoutes())->contains(
            fn ($route) => str_ends_with((string) $route->uri(), 'robots.txt') && $route->domain() !== null && str_contains((string) $route->domain(), 'central')
        );

        $this->assertFalse($registered, 'No central-domain robots route should register while central.domain is unconfigured.');
    }

    /**
     * K-WEB-P2-ROBOTS-001C — real-HTTP verification of K-WEB-P2-ROBOTS-001B
     * found that `RejectUnsupportedKeryonHost` admitted only
     * `filament.central.*` on the Central host, so the Central robots
     * route registered correctly but was unconditionally 404'd before it
     * could ever respond. The route was given the exact, stable name
     * `platform-central.robots` and the middleware now admits that name
     * specifically — nothing broader. These three tests exercise the
     * middleware directly (the same pattern `PlatformMfaSecurityTest`
     * already uses for this exact host), since `central.domain` has no
     * default and a live domain-bound route cannot register in this test
     * environment — see `test_central_robots_route_registration_is_guarded_by_configured_domain`
     * above for why, and the K-WEB-P2-ROBOTS-001C report for the real-HTTP
     * proof this can't stand in for.
     */
    public function test_central_robots_route_is_explicitly_admitted_by_the_host_trust_middleware(): void
    {
        config(['central.domain' => 'central.keryon.app']);

        $response = app(RejectUnsupportedKeryonHost::class)->handle(
            $this->centralHostRequest('platform-central.robots'),
            fn () => response('ok'),
        );

        $this->assertSame('ok', $response->getContent());
    }

    public function test_an_arbitrary_central_route_name_remains_rejected(): void
    {
        config(['central.domain' => 'central.keryon.app']);
        $this->expectException(HttpException::class);

        app(RejectUnsupportedKeryonHost::class)->handle(
            $this->centralHostRequest('some-unapproved-route'),
            fn () => response('unsafe'),
        );
    }

    public function test_filament_central_admission_remains_intact(): void
    {
        config(['central.domain' => 'central.keryon.app']);

        $response = app(RejectUnsupportedKeryonHost::class)->handle(
            $this->centralHostRequest('filament.central.central-home'),
            fn () => response('ok'),
        );

        $this->assertSame('ok', $response->getContent());
    }

    public function test_published_church_subdomain_advertises_its_own_sitemap(): void
    {
        $church = $this->publishedChurch('Grace Church', 'grace');

        $response = $this->get('http://grace.keryon.app/robots.txt');

        $response->assertOk()->assertContent("User-agent: *\nAllow: /\nSitemap: https://grace.keryon.app/sitemap.xml\n");
    }

    public function test_unpublished_church_has_no_public_robots_resource(): void
    {
        $church = Church::create(['name' => 'Draft Church', 'slug' => 'draft-church', 'is_active' => true, 'activated_at' => now()]);
        $communications = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($communications);
        WebsiteSettings::create([]);
        WebsiteHomeContent::create(['hero_heading' => 'Working draft only']);

        $this->get('http://draft-church.keryon.app/robots.txt')->assertNotFound();
    }

    public function test_two_church_subdomains_never_leak_each_others_robots_authority(): void
    {
        $this->publishedChurch('Church A', 'robots-host-a');
        $this->publishedChurch('Church B', 'robots-host-b');

        $responseA = $this->get('http://robots-host-a.keryon.app/robots.txt')->assertOk();
        $responseB = $this->get('http://robots-host-b.keryon.app/robots.txt')->assertOk();

        $this->assertStringContainsString('robots-host-a.keryon.app', $responseA->getContent());
        $this->assertStringNotContainsString('robots-host-b', $responseA->getContent());
        $this->assertStringContainsString('robots-host-b.keryon.app', $responseB->getContent());
        $this->assertStringNotContainsString('robots-host-a', $responseB->getContent());
    }

    public function test_primary_custom_domain_robots_and_sitemap_agree_on_the_custom_host(): void
    {
        $church = $this->publishedChurch('Custom Robots Church', 'custom-robots-church');
        $this->activeDomain($church, 'custom-robots.org', primary: true);

        $robots = $this->get('http://custom-robots.org/robots.txt')->assertOk();
        $robots->assertContent("User-agent: *\nAllow: /\nSitemap: https://custom-robots.org/sitemap.xml\n");

        $this->get('http://custom-robots.org/sitemap.xml')->assertOk();
    }

    public function test_non_primary_custom_alias_redirects_its_robots_to_the_canonical_host(): void
    {
        $church = $this->publishedChurch('Alias Robots Church', 'alias-robots-church');
        $this->activeDomain($church, 'alias-robots.org', primary: true);
        $this->activeDomain($church, 'www.alias-robots.org');

        $this->get('http://www.alias-robots.org/robots.txt')
            ->assertStatus(308)
            ->assertRedirect('https://alias-robots.org/robots.txt');
    }

    private function centralHostRequest(string $routeName): Request
    {
        $request = Request::create('https://central.keryon.app/'.ltrim($routeName, '/'));
        $route = new RoutingRoute('GET', '/{any}', ['as' => $routeName]);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function publishedChurch(string $name, string $slug, string $heading = 'Published heading'): Church
    {
        $church = Church::create(['name' => $name, 'slug' => $slug, 'is_active' => true, 'activated_at' => now()]);
        $publisher = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($publisher);
        app(TenantContext::class)->forgetResolved();
        WebsiteSettings::create([]);
        WebsiteHomeContent::create(['hero_heading' => $heading]);
        app(WebsitePublisher::class)->publish();

        return $church;
    }

    private function activeDomain(Church $church, string $hostname, bool $primary = false): ChurchDomain
    {
        $administrator = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($administrator);
        app(TenantContext::class)->forgetResolved();

        $domain = app(RequestChurchCustomDomain::class)->execute($church, $hostname)->domain;
        $lifecycle = app(ChurchDomainLifecycle::class);
        $domain = $lifecycle->ownershipVerified($domain);
        $domain = $lifecycle->routingVerified($domain);
        $domain = $lifecycle->tlsProvisioning($domain);
        $domain = $lifecycle->tlsReady($domain);
        $domain = $lifecycle->activate($domain);

        return $primary ? app(MakeChurchDomainPrimary::class)->execute($domain) : $domain;
    }
}
