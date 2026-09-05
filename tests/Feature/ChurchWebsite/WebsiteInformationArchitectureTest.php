<?php

namespace Tests\Feature\ChurchWebsite;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Enums\ChurchRole;
use App\Enums\DomainStatus;
use App\Enums\DomainTlsStatus;
use App\Enums\DomainVerificationMethod;
use App\Filament\Clusters\Website\Pages\EditBrand;
use App\Filament\Clusters\Website\Pages\EditHome;
use App\Filament\Clusters\Website\Pages\ManageDomains;
use App\Filament\Clusters\Website\Pages\WebsiteOverview;
use App\Filament\Clusters\Website\WebsiteNavigation;
use App\Livewire\KeryonWorkspaceHeader;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsitePublication;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class WebsiteInformationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $entitlements = Mockery::mock(EntitlementResolver::class);
        $entitlements->shouldReceive('allows')->andReturnTrue();
        $this->app->instance(EntitlementResolver::class, $entitlements);
    }

    public function test_navigation_is_grouped_and_domains_is_discoverable_only_to_primary_administrator(): void
    {
        $church = Church::factory()->create(['slug' => 'navigation-church']);
        $primary = User::factory()->asPrimaryAdministratorOf($church)->create();
        $this->actingAs($primary);

        $this->assertSame(WebsiteNavigation::CONTENT, EditHome::getNavigationGroup());
        $this->assertSame(WebsiteNavigation::SITE_IDENTITY, EditBrand::getNavigationGroup());
        $this->assertSame(WebsiteNavigation::CONFIGURATION, ManageDomains::getNavigationGroup());
        Livewire::test(WebsiteOverview::class)
            ->assertSee('Content')
            ->assertSee('Site identity')
            ->assertSee('Configuration')
            ->assertSeeHtml('href="'.ManageDomains::getUrl().'"');

        $communications = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($communications);
        app(TenantContext::class)->forgetResolved();
        Livewire::test(WebsiteOverview::class)
            ->assertSee('Domains')
            // K-WEB-V1-001B §13 — the plain-language explanation replaces
            // the earlier bare "View only" label so a Communications user
            // understands *why* Domains is read-only, without exposing
            // capability/policy terminology.
            ->assertSee('Only your Primary Administrator can manage website domains.')
            ->assertDontSeeHtml('href="'.ManageDomains::getUrl().'"');

        foreach ([ChurchRole::CARE, ChurchRole::ADMINISTRATOR] as $role) {
            $user = User::factory()->forChurch($church, [$role], primary: false)->create();
            $this->actingAs($user);
            app(TenantContext::class)->forgetResolved();
            $this->assertFalse(ManageDomains::canAccess());
        }
    }

    public function test_overview_shows_keryon_address_without_publishing_or_mutating_domain_state(): void
    {
        [$church] = $this->websiteUser('quiet-harbour');
        $before = WebsitePublication::count();

        Livewire::test(WebsiteOverview::class)
            ->assertSee('Your Website is ready to set up.')
            ->assertSee('Not published yet')
            ->assertSee('https://quiet-harbour.keryon.app')
            ->assertSee('Using your Keryon address')
            ->assertSee('Pages')
            ->assertSee('Site setup');

        $this->assertSame($before, WebsitePublication::count());
        $this->assertSame(0, ChurchDomain::count());
    }

    public function test_overview_presents_pending_connected_and_degraded_domain_states_truthfully(): void
    {
        [$church] = $this->websiteUser('domain-states', primary: true);
        $domain = $this->domain($church, 'a-very-long-domain-name-for-domain-states.example.org', DomainStatus::PendingVerification);

        Livewire::test(WebsiteOverview::class)
            ->assertSee('a-very-long-domain-name-for-domain-states.example.org')
            ->assertSee('Waiting for verification')
            ->assertSee('Continue setup');

        $domain->forceFill([
            'status' => DomainStatus::Active,
            'ownership_verified_at' => now(),
            'routing_verified_at' => now(),
            'tls_status' => DomainTlsStatus::Ready,
            'tls_ready_at' => now(),
            'activated_at' => now(),
            'is_primary' => true,
        ])->save();
        Livewire::test(WebsiteOverview::class)
            ->assertSee('Connected')
            ->assertSee('Keryon fallback: https://domain-states.keryon.app');

        $domain->forceFill(['status' => DomainStatus::Degraded, 'failure_code' => 'dns_mismatch'])->save();
        Livewire::test(WebsiteOverview::class)
            ->assertSee('Custom domain needs attention')
            ->assertSee('Your Keryon address remains available.')
            ->assertSee('Review domains');
    }

    public function test_header_uses_preview_until_published_then_canonical_custom_or_fallback_url(): void
    {
        [$church] = $this->websiteUser('header-behaviour', primary: true);
        $domain = $this->domain($church, 'www.header-behaviour.example.org', DomainStatus::Active, primary: true, healthy: true);

        Livewire::test(KeryonWorkspaceHeader::class)
            ->assertSee('Preview Website')
            ->assertSeeHtml('href="'.route('website.preview').'"')
            ->assertDontSee('Go to Website');
        $this->assertSame(0, WebsitePublication::count());

        app(WebsitePublisher::class)->publish();
        Livewire::test(KeryonWorkspaceHeader::class)
            ->assertSee('Go to Website')
            ->assertSeeHtml('href="https://www.header-behaviour.example.org"')
            ->assertDontSee('Preview Website');

        $domain->forceFill(['status' => DomainStatus::Degraded, 'failure_code' => 'dns_mismatch'])->save();
        Livewire::test(KeryonWorkspaceHeader::class)
            ->assertSee('Go to Website')
            ->assertSeeHtml('href="https://header-behaviour.keryon.app"');
    }

    /** @return array{Church,User} */
    private function websiteUser(string $slug, bool $primary = false): array
    {
        $church = Church::factory()->create(['slug' => $slug]);
        $user = User::factory()->forChurch(
            $church,
            $primary ? [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS] : [ChurchRole::COMMUNICATIONS],
            $primary,
        )->create();
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();
        WebsiteSettings::create([]);
        WebsiteHomeContent::create([]);

        return [$church, $user];
    }

    private function domain(
        Church $church,
        string $hostname,
        DomainStatus $status,
        bool $primary = false,
        bool $healthy = false,
    ): ChurchDomain {
        $domain = new ChurchDomain([
            'normalized_hostname' => $hostname,
            'display_hostname' => $hostname,
            'status' => $status,
            'verification_method' => DomainVerificationMethod::Txt,
            'verification_token_hash' => hash('sha256', 'test-token'),
            'tls_status' => $healthy ? DomainTlsStatus::Ready : DomainTlsStatus::NotStarted,
            'is_primary' => $primary,
            'ownership_verified_at' => $healthy ? now() : null,
            'routing_verified_at' => $healthy ? now() : null,
            'tls_ready_at' => $healthy ? now() : null,
            'activated_at' => $healthy ? now() : null,
        ]);
        $domain->church_id = $church->id;
        $domain->save();

        return $domain;
    }
}
