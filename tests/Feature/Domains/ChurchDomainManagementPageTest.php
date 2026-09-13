<?php

namespace Tests\Feature\Domains;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Domain\ChurchDomainLifecycle;
use App\Domain\ChurchDomainStatusPresenter;
use App\Domain\Dns\DnsResolver;
use App\Domain\Dns\FakeDnsResolver;
use App\Domain\Provisioning\DomainProvisioner;
use App\Domain\Provisioning\FakeDomainProvisioner;
use App\Domain\Provisioning\ProvisioningStatus;
use App\Domain\RequestChurchCustomDomain;
use App\Enums\ChurchRole;
use App\Enums\DomainFailureCode;
use App\Enums\DomainStatus;
use App\Enums\DomainTlsStatus;
use App\Enums\DomainVerificationMethod;
use App\Enums\MembershipStatus;
use App\Filament\Clusters\Website\Pages\ManageDomains;
use App\Filament\Clusters\Website\Pages\WebsiteOverview;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsitePublication;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ChurchDomainManagementPageTest extends TestCase
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

    public function test_only_current_primary_administrator_can_access_page(): void
    {
        $church = Church::factory()->create();
        $primaryAdmin = $this->churchUser($church, [ChurchRole::ADMINISTRATOR], true);
        $this->assertTrue(ManageDomains::canAccess());
        Livewire::test(ManageDomains::class)->assertSuccessful()->assertSee('Your Church Website addresses');

        foreach ([
            [[ChurchRole::ADMINISTRATOR], false],
            [[ChurchRole::COMMUNICATIONS], true],
            [[ChurchRole::CARE], true],
        ] as [$roles, $primary]) {
            $this->churchUser($church, $roles, $primary);
            $this->assertFalse(ManageDomains::canAccess());
        }

        $organization = Organization::query()->create(['name' => 'Domain Organization', 'slug' => 'domain-organization', 'status' => 'active']);
        $organizationUser = User::factory()->create();
        OrganizationMembership::query()->create(['organization_id' => $organization->id, 'user_id' => $organizationUser->id, 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($organizationUser);
        app(TenantContext::class)->forgetResolved();
        $this->assertFalse(ManageDomains::canAccess());

        $this->actingAs($primaryAdmin);
    }

    public function test_empty_state_claim_normalization_limit_and_one_time_token_are_truthful(): void
    {
        $church = Church::factory()->create(['slug' => 'grace-hall']);
        $this->churchUser($church, [ChurchRole::ADMINISTRATOR], true);

        $component = Livewire::test(ManageDomains::class)
            ->assertSee('https://grace-hall.keryon.app')
            ->assertSee('Connect your domain')
            ->set('hostname', 'WWW.GraceHall.org.')
            ->call('claim')
            ->assertHasNoErrors()
            ->assertSee('www.gracehall.org')
            ->assertSee('data-testid="verification-token"', false);

        $rawToken = $component->get('verificationToken');
        $domain = ChurchDomain::query()->firstOrFail();
        $this->assertNotNull($rawToken);
        $this->assertSame(hash('sha256', $rawToken), $domain->verification_token_hash);
        $this->assertDatabaseMissing('church_domains', ['verification_token_hash' => $rawToken]);

        Livewire::test(ManageDomains::class)
            ->assertSet('verificationToken', null)
            ->assertDontSee($rawToken)
            ->assertSee('verification value is not stored');

        Livewire::test(ManageDomains::class)->set('hostname', 'gracehall.org')->call('claim')->assertHasNoErrors();
        Livewire::test(ManageDomains::class)
            ->assertDontSee('Add a companion address')
            ->assertSee('one primary custom domain and one companion address')
            ->set('hostname', 'third.gracehall.org')->call('claim')->assertHasErrors(['hostname']);
    }

    public function test_validation_copy_is_safe_and_specific(): void
    {
        $church = Church::factory()->create();
        $this->churchUser($church, [ChurchRole::ADMINISTRATOR], true);

        Livewire::test(ManageDomains::class)->set('hostname', 'https://church.org/path')->call('claim')
            ->assertHasErrors(['hostname'])->assertSee('Enter only the domain name');
        Livewire::test(ManageDomains::class)->set('hostname', 'église.org')->call('claim')
            ->assertHasErrors(['hostname'])->assertSee("Internationalized domain names aren't supported yet");
        Livewire::test(ManageDomains::class)->set('hostname', 'app.keryon.app')->call('claim')
            ->assertHasErrors(['hostname'])->assertSee("This Keryon address can't be used");
    }

    public function test_regeneration_is_authorized_invalidates_old_value_and_never_reconstructs_it(): void
    {
        $church = Church::factory()->create();
        $this->churchUser($church, [ChurchRole::ADMINISTRATOR], true);
        $first = app(RequestChurchCustomDomain::class)->execute($church, 'verify.example.org');

        $component = Livewire::test(ManageDomains::class)->call('regenerate', $first->domain->id);
        $newToken = $component->get('verificationToken');
        $this->assertNotSame($first->verificationToken, $newToken);
        $this->assertSame(hash('sha256', $newToken), $first->domain->fresh()->verification_token_hash);
        $component->assertDontSee($first->verificationToken);
    }

    public function test_verification_uses_job_and_keeps_ownership_routing_and_https_distinct(): void
    {
        config()->set('public-website.custom_domains.dns_resolver', 'fake');
        config()->set('public-website.custom_domains.provisioner', 'fake');
        config()->set('public-website.custom_domains.dns_ingress_target', 'domains.keryon.test');
        $dns = new FakeDnsResolver;
        $provisioner = (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready);
        $this->app->instance(DnsResolver::class, $dns);
        $this->app->instance(DomainProvisioner::class, $provisioner);

        $church = Church::factory()->create();
        $this->churchUser($church, [ChurchRole::ADMINISTRATOR], true);
        $claim = app(RequestChurchCustomDomain::class)->execute($church, 'www.ready-church.org');
        $dns->setTxt($claim->verificationHostname, [$claim->verificationToken])
            ->setCname('www.ready-church.org', ['domains.keryon.test']);

        Livewire::test(ManageDomains::class)->call('verify', $claim->domain->id)->assertNotified('Verification check started');
        $domain = $claim->domain->fresh();
        $this->assertNotNull($domain->ownership_verified_at);
        $this->assertNotNull($domain->routing_verified_at);
        $this->assertTrue($domain->isEligible());

        Livewire::test(ManageDomains::class)
            ->assertSee('Ownership')->assertSee('Verified')
            ->assertSee('Website connection')->assertSee('Connected')
            ->assertSee('HTTPS ready')->assertSee('Make primary');
    }

    public function test_unavailable_environment_never_offers_fake_activation(): void
    {
        config()->set('public-website.custom_domains.dns_resolver', 'unavailable');
        config()->set('public-website.custom_domains.provisioner', 'unavailable');
        config()->set('public-website.custom_domains.dns_ingress_target', null);
        $church = Church::factory()->create();
        $this->churchUser($church, [ChurchRole::ADMINISTRATOR], true);
        app(RequestChurchCustomDomain::class)->execute($church, 'waiting.example.org');

        Livewire::test(ManageDomains::class)
            ->assertSee('activation is not yet available in this environment')
            ->assertDontSee('Check connection');
    }

    public function test_failure_and_degraded_copy_are_bounded_and_keep_fallback_visible(): void
    {
        $church = Church::factory()->create(['slug' => 'healthy-fallback']);
        $this->churchUser($church, [ChurchRole::ADMINISTRATOR], true);
        $domain = app(RequestChurchCustomDomain::class)->execute($church, 'attention.example.org')->domain;
        $presenter = app(ChurchDomainStatusPresenter::class);

        foreach ([
            [DomainFailureCode::DnsNotConfigured, 'required DNS record is not visible'],
            [DomainFailureCode::DnsMismatch, 'does not match'],
            [DomainFailureCode::DnsLookupTimeout, 'could not check your DNS'],
            [DomainFailureCode::CertificatePending, 'still being prepared'],
            [DomainFailureCode::CertificateFailed, 'needs attention'],
            [DomainFailureCode::ProviderUnavailable, 'temporarily unavailable'],
        ] as [$code, $copy]) {
            $domain->forceFill(['failure_code' => $code->value])->save();
            $this->assertStringContainsString($copy, $presenter->failureMessage($domain->fresh()));
        }

        $domain->forceFill(['failure_code' => null])->save();
        $lifecycle = app(ChurchDomainLifecycle::class);
        $domain = $lifecycle->ownershipVerified($domain);
        $domain = $lifecycle->routingVerified($domain);
        $domain = $lifecycle->tlsProvisioning($domain);
        $domain = $lifecycle->tlsReady($domain);
        $domain = $lifecycle->activate($domain);
        $domain->forceFill(['consecutive_failures' => 3, 'failure_streak_started_at' => now()->subDay()->subMinute()])->save();
        app(ChurchDomainLifecycle::class)->degrade($domain, DomainFailureCode::DnsMismatch);

        Livewire::test(ManageDomains::class)
            ->assertSee('Custom domain needs attention')
            ->assertSee('https://healthy-fallback.keryon.app')
            ->assertSee('Website content is safe');
    }

    public function test_primary_disable_release_degraded_and_overview_states_use_canonical_services(): void
    {
        $church = Church::factory()->create(['slug' => 'resilient-church']);
        $this->churchUser($church, [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS], true);
        WebsiteSettings::query()->create([]);
        WebsiteHomeContent::query()->create(['hero_heading' => 'Immutable public content']);
        app(WebsitePublisher::class)->publish();
        $domain = $this->activeDomain($church, 'resilient.org');

        Livewire::test(ManageDomains::class)->call('makePrimary', $domain->id)
            ->assertSee('https://resilient.org')->assertSee('https://resilient-church.keryon.app');
        Livewire::test(WebsiteOverview::class)->assertSee('https://resilient.org')->assertSee('Manage domains');
        $publicationId = WebsiteSettings::query()->value('current_publication_id');

        Livewire::test(ManageDomains::class)->call('disable', $domain->id)
            ->assertSee('https://resilient-church.keryon.app');
        $this->assertSame($publicationId, WebsiteSettings::query()->value('current_publication_id'));
        $this->assertSame(1, WebsitePublication::query()->count());

        Livewire::test(ManageDomains::class)->call('release', $domain->id)
            ->assertSee('Previous domains')->assertSee('30-day quarantine');
        $this->assertDatabaseHas('church_domains', ['id' => $domain->id, 'status' => DomainStatus::Released->value]);
    }

    public function test_cross_church_stale_membership_and_primary_transfer_fail_closed(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $oldPrimary = $this->churchUser($churchA, [ChurchRole::ADMINISTRATOR], true);
        $domainB = new ChurchDomain;
        $domainB->forceFill([
            'church_id' => $churchB->id,
            'normalized_hostname' => 'church-b.example.org',
            'status' => DomainStatus::PendingVerification,
            'verification_method' => DomainVerificationMethod::Txt,
            'verification_token_hash' => hash('sha256', 'church-b-token'),
            'tls_status' => DomainTlsStatus::NotStarted,
        ])->save();

        try {
            Livewire::test(ManageDomains::class)->instance()->regenerate($domainB->id);
            $this->fail('A cross-Church domain action must fail closed.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $membership = $oldPrimary->memberships()->where('church_id', $churchA->id)->firstOrFail();
        $component = Livewire::test(ManageDomains::class);
        $membership->update(['is_primary' => false]);
        app(TenantContext::class)->forgetResolved();
        $this->assertActionIsForbidden($component->instance(), 'stale.example.org');

        $membership->update(['is_primary' => true, 'status' => MembershipStatus::SUSPENDED]);
        app(TenantContext::class)->forgetResolved();
        $this->assertActionIsForbidden($component->instance(), 'suspended.example.org');

        $membership->update(['status' => MembershipStatus::REMOVED]);
        app(TenantContext::class)->forgetResolved();
        $this->assertActionIsForbidden($component->instance(), 'removed.example.org');

        $newPrimary = $this->churchUser($churchA, [ChurchRole::ADMINISTRATOR], true);
        Livewire::test(ManageDomains::class)->set('hostname', 'new-primary.example.org')->call('claim')->assertHasNoErrors();
        $this->actingAs($newPrimary);
    }

    public function test_page_render_is_bounded_and_never_checks_dns(): void
    {
        $church = Church::factory()->create();
        $this->churchUser($church, [ChurchRole::ADMINISTRATOR], true);
        app(RequestChurchCustomDomain::class)->execute($church, 'one.example.org');
        app(RequestChurchCustomDomain::class)->execute($church, 'two.example.org');

        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(ManageDomains::class)->assertSuccessful();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // K-WEB-V1-001D-C: the Website panel now includes four additional
        // capability-gated curated Website destinations (Events, Messages,
        // Publications, Giving). Filament evaluates their navigation
        // authorization during panel rendering, adding four bounded
        // capability queries under the current authorization implementation.
        // Tracked as K-AUTH-PERF-001 (capability-resolution deduplication);
        // not this milestone's concern to fix.
        $this->assertLessThanOrEqual(22, count($queries));
        $this->assertSame(0, collect($queries)->filter(fn (array $query) => str_contains(strtolower($query['query']), 'church_domain_events'))->count());
    }

    private function churchUser(Church $church, array $roles, bool $primary): User
    {
        $user = User::factory()->forChurch($church, $roles, primary: $primary)->create();
        $this->actingAs($user);
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();

        return $user;
    }

    private function activeDomain(Church $church, string $hostname): ChurchDomain
    {
        $domain = app(RequestChurchCustomDomain::class)->execute($church, $hostname)->domain;
        $lifecycle = app(ChurchDomainLifecycle::class);
        $domain = $lifecycle->ownershipVerified($domain);
        $domain = $lifecycle->routingVerified($domain);
        $domain = $lifecycle->tlsProvisioning($domain);
        $domain = $lifecycle->tlsReady($domain);

        return $lifecycle->activate($domain);
    }

    private function assertActionIsForbidden(ManageDomains $page, string $hostname): void
    {
        $page->hostname = $hostname;
        try {
            $page->claim();
            $this->fail('A stale domain-management action must fail closed.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }
}
