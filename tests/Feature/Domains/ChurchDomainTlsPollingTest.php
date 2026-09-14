<?php

namespace Tests\Feature\Domains;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Domain\ChurchDomainLifecycle;
use App\Domain\Provisioning\DomainProvisioner;
use App\Domain\Provisioning\FakeDomainProvisioner;
use App\Domain\Provisioning\ProvisioningStatus;
use App\Domain\RequestChurchCustomDomain;
use App\Enums\ChurchRole;
use App\Enums\DomainFailureCode;
use App\Enums\DomainStatus;
use App\Enums\DomainTlsStatus;
use App\Jobs\PollChurchDomainTls;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChurchDomainTlsPollingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public-website.custom_domains.dns_ingress_target', 'ingress.keryon.app');
        // This file exercises domain lifecycle/health/scheduling behaviour,
        // not entitlement gating — allow every entitlement so claim/activate
        // fixtures succeed. Entitlement enforcement itself is covered by
        // CustomDomainEntitlementTest (K-DOMAIN-001F).
        $entitlements = Mockery::mock(EntitlementResolver::class);
        $entitlements->shouldReceive('allows')->andReturnTrue();
        $this->app->instance(EntitlementResolver::class, $entitlements);
    }

    public function test_pending_leaves_domain_in_provisioning(): void
    {
        $domain = $this->provisioningDomain('pending.org');
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Pending));

        $this->app->call([new PollChurchDomainTls($domain->id), 'handle']);

        $fresh = $domain->fresh();
        $this->assertSame(DomainTlsStatus::Provisioning, $fresh->tls_status);
        $this->assertNotSame(DomainStatus::Active, $fresh->status);
    }

    public function test_ready_becomes_ready_and_activates_when_prerequisites_hold(): void
    {
        $domain = $this->provisioningDomain('ready.org');
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));

        $this->app->call([new PollChurchDomainTls($domain->id), 'handle']);

        $fresh = $domain->fresh();
        $this->assertSame(DomainTlsStatus::Ready, $fresh->tls_status);
        $this->assertNotNull($fresh->tls_ready_at);
        $this->assertSame(DomainStatus::Active, $fresh->status);
        $this->assertNotNull($fresh->activated_at);
    }

    public function test_ready_does_not_activate_when_church_is_no_longer_active(): void
    {
        $domain = $this->provisioningDomain('inactive-church.org');
        Church::query()->where('id', $domain->church_id)->update(['is_active' => false]);
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));

        $this->app->call([new PollChurchDomainTls($domain->id), 'handle']);

        $fresh = $domain->fresh();
        $this->assertSame(DomainTlsStatus::Ready, $fresh->tls_status);
        $this->assertNotSame(DomainStatus::Active, $fresh->status, 'activation must re-check prerequisites, not just TLS readiness');
    }

    public function test_failed_becomes_terminal_tls_failure(): void
    {
        $domain = $this->provisioningDomain('failed.org');
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Failed));

        $this->app->call([new PollChurchDomainTls($domain->id), 'handle']);

        $fresh = $domain->fresh();
        $this->assertSame(DomainTlsStatus::Failed, $fresh->tls_status);
        $this->assertSame(DomainFailureCode::CertificateFailed->value, $fresh->failure_code);
    }

    public function test_unavailable_remains_retriable_and_is_not_a_certificate_failure(): void
    {
        $domain = $this->provisioningDomain('unavailable.org');
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Unavailable));

        $this->app->call([new PollChurchDomainTls($domain->id), 'handle']);

        $fresh = $domain->fresh();
        $this->assertSame(DomainTlsStatus::Provisioning, $fresh->tls_status);
        $this->assertNotSame(DomainFailureCode::CertificateFailed->value, $fresh->failure_code);
    }

    public function test_disabled_domain_is_ignored(): void
    {
        $domain = $this->provisioningDomain('disabled-poll.org');
        $domain->forceFill(['disabled_at' => now()])->save();
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));

        $this->app->call([new PollChurchDomainTls($domain->id), 'handle']);

        $this->assertSame(DomainTlsStatus::Provisioning, $domain->fresh()->tls_status);
    }

    public function test_released_domain_is_ignored(): void
    {
        $domain = $this->provisioningDomain('released-poll.org');
        $domain->forceFill(['released_at' => now()])->save();
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));

        $this->app->call([new PollChurchDomainTls($domain->id), 'handle']);

        $this->assertSame(DomainTlsStatus::Provisioning, $domain->fresh()->tls_status);
    }

    public function test_domain_no_longer_provisioning_is_ignored(): void
    {
        $domain = $this->provisioningDomain('already-ready.org');
        $lifecycle = app(ChurchDomainLifecycle::class);
        $lifecycle->tlsReady($domain);
        $lifecycle->activate($domain->fresh());
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Failed));

        // Should no-op — tls_status is already Ready, not Provisioning.
        $this->app->call([new PollChurchDomainTls($domain->id), 'handle']);

        $this->assertSame(DomainTlsStatus::Ready, $domain->fresh()->tls_status);
    }

    private function provisioningDomain(string $hostname): ChurchDomain
    {
        $church = Church::create(['name' => $hostname, 'slug' => str_replace('.', '-', $hostname), 'activated_at' => now(), 'is_active' => true]);
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($user);
        $claim = app(RequestChurchCustomDomain::class)->execute($church, $hostname);

        $lifecycle = app(ChurchDomainLifecycle::class);
        $domain = $lifecycle->ownershipVerified($claim->domain);
        $domain = $lifecycle->routingVerified($domain);

        return $lifecycle->tlsProvisioning($domain);
    }
}
