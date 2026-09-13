<?php

namespace Tests\Feature\Domains;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Domain\ChurchDomainLifecycle;
use App\Domain\MakeChurchDomainPrimary;
use App\Domain\RequestChurchCustomDomain;
use App\Enums\Capability;
use App\Enums\ChurchRole;
use App\Enums\DomainFailureCode;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use App\Website\ChurchPublicUrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class CustomDomainRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $entitlements = Mockery::mock(EntitlementResolver::class);
        $entitlements->shouldReceive('allows')->andReturnTrue();
        $this->app->instance(EntitlementResolver::class, $entitlements);
    }

    public function test_platform_unknown_and_first_party_hosts_are_governed(): void
    {
        $church = $this->publishedChurch('First Party Church', 'first-party');

        $this->get('http://keryon.app')->assertOk();
        $this->get('http://www.keryon.app/about?from=www')
            ->assertStatus(308)
            ->assertRedirect('https://keryon.app/about?from=www');
        $this->get('http://central.keryon.app')->assertNotFound();
        $this->get('http://app.keryon.app')->assertNotFound();
        $this->get('http://unknown.example')->assertNotFound();
        $this->get('http://unknown.example/features')->assertNotFound();
        $this->get('http://unknown.keryon.app')->assertNotFound();
        $this->get('http://first-party.keryon.app')->assertOk()->assertSee('First Party Church');
    }

    public function test_active_custom_host_serves_same_publication_with_relative_navigation_and_custom_seo(): void
    {
        $church = $this->publishedChurch('Custom Church', 'custom-church', 'Immutable published heading');
        $primary = $this->activeDomain($church, 'www.custom-church.org', primary: true);

        $response = $this->get('http://www.custom-church.org');
        $response->assertOk()
            ->assertSee('Immutable published heading')
            ->assertSee('rel="canonical" href="https://www.custom-church.org"', false)
            ->assertSee('property="og:url" content="https://www.custom-church.org"', false)
            ->assertSee('"url":"https://www.custom-church.org"', false)
            ->assertSee('href="/about"', false)
            ->assertDontSee('href="https://custom-church.keryon.app/about"', false);

        foreach (['about', 'leadership', 'ministries', 'contact'] as $page) {
            $this->get("http://www.custom-church.org/{$page}")->assertOk();
        }

        $this->assertTrue($primary->fresh()->is_primary);
    }

    public function test_keryon_fallback_serves_but_emits_custom_canonical_and_sitemap_robots_agree(): void
    {
        $church = $this->publishedChurch('Fallback Church', 'fallback-church');
        $this->activeDomain($church, 'fallback.org', primary: true);

        $this->assertSame('https://fallback.org', app(ChurchPublicUrlResolver::class)->resolve($church));

        $this->get('http://fallback-church.keryon.app')
            ->assertOk()
            ->assertSee('rel="canonical" href="https://fallback.org"', false);
        $this->get('http://fallback-church.keryon.app/sitemap.xml')
            ->assertOk()
            ->assertSee('https://fallback.org/contact');
        $this->get('http://fallback-church.keryon.app/robots.txt')
            ->assertOk()
            ->assertSee('https://fallback.org/sitemap.xml');
    }

    public function test_alias_redirects_with_path_and_query_while_pending_disabled_released_and_degraded_hosts_fail(): void
    {
        $church = $this->publishedChurch('Alias Church', 'alias-church');
        $primary = $this->activeDomain($church, 'alias.org', primary: true);
        $alias = $this->activeDomain($church, 'www.alias.org');

        $this->get('http://www.alias.org/about?campaign=sunday')
            ->assertStatus(308)
            ->assertRedirect('https://alias.org/about?campaign=sunday');

        app(ChurchDomainLifecycle::class)->disable($primary, app(TenantContext::class)->currentMembership());
        $this->get('http://alias.org')->assertNotFound();
        $this->get('http://www.alias.org')->assertStatus(308)->assertRedirect('https://alias-church.keryon.app');
        $this->assertSame('https://alias-church.keryon.app', app(ChurchPublicUrlResolver::class)->resolve($church));

        app(ChurchDomainLifecycle::class)->release($alias, app(TenantContext::class)->currentMembership());
        $this->get('http://www.alias.org')->assertNotFound();

        $pending = app(RequestChurchCustomDomain::class)->execute($church, 'pending.alias.org')->domain;
        $this->get('http://pending.alias.org')->assertNotFound();
        $this->assertFalse($pending->isEligible());
    }

    public function test_degraded_primary_reverts_canonical_without_content_mutation(): void
    {
        $church = $this->publishedChurch('Degraded Church', 'degraded-church', 'Published truth');
        $domain = $this->activeDomain($church, 'degraded.org', primary: true);
        $domain->forceFill(['consecutive_failures' => 3, 'failure_streak_started_at' => now()->subDay()->subMinute()])->save();
        app(ChurchDomainLifecycle::class)->degrade($domain, DomainFailureCode::DnsMismatch);

        $this->get('http://degraded.org')->assertNotFound();
        $this->get('http://degraded-church.keryon.app')
            ->assertOk()
            ->assertSee('Published truth')
            ->assertSee('rel="canonical" href="https://degraded-church.keryon.app"', false);
    }

    public function test_no_publication_is_404_and_custom_host_never_exposes_working_state_or_creates_publication(): void
    {
        $church = Church::create(['name' => 'Private Church', 'slug' => 'private-domain', 'is_active' => true, 'activated_at' => now()]);
        $communications = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($communications);
        WebsiteSettings::create([]);
        WebsiteHomeContent::create(['hero_heading' => 'Private working draft']);
        $this->activeDomain($church, 'private.org', primary: true);

        $this->get('http://private.org')->assertNotFound()->assertDontSee('Private working draft');
        $this->assertSame(0, DB::table('website_publications')->where('church_id', $church->id)->count());
    }

    public function test_sequential_custom_hosts_and_authenticated_tenant_context_do_not_leak(): void
    {
        $churchA = $this->publishedChurch('Church A', 'host-a', 'A publication');
        $this->activeDomain($churchA, 'a.example.org', primary: true);
        $churchB = $this->publishedChurch('Church B', 'host-b', 'B publication');
        $this->activeDomain($churchB, 'b.example.org', primary: true);

        $memberA = User::factory()->forChurch($churchA, [ChurchRole::CARE])->create();
        $this->actingAs($memberA);
        app(TenantContext::class)->forgetResolved();

        $this->get('http://b.example.org')->assertOk()->assertSee('B publication')->assertDontSee('A publication');
        $this->get('http://a.example.org')->assertOk()->assertSee('A publication')->assertDontSee('B publication');
        $this->get('http://missing.example.org')->assertNotFound();
        $this->assertSame($churchA->id, app(TenantContext::class)->currentChurchId());
    }

    public function test_first_party_custom_alias_and_canonical_resolvers_have_bounded_queries(): void
    {
        $church = $this->publishedChurch('Query Church', 'query-church');
        $this->activeDomain($church, 'query.org', primary: true);
        $this->activeDomain($church, 'www.query.org');

        foreach ([
            'http://query-church.keryon.app' => 4,
            'http://query.org' => 4,
            'http://www.query.org/about' => 4,
        ] as $url => $ceiling) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $this->assertLessThanOrEqual($ceiling, count($queries), "Query ceiling exceeded for {$url}: ".count($queries));
        }
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
        $membership = app(TenantContext::class)->currentMembership();
        if ($membership === null || $membership->church_id !== $church->id || ! $membership->is_primary
            || ! $membership->hasCapability(Capability::WebsiteDomainManage)) {
            $administrator = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
            $this->actingAs($administrator);
            app(TenantContext::class)->forgetResolved();
        }
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
