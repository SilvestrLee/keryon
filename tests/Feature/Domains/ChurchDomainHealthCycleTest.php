<?php

namespace Tests\Feature\Domains;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Domain\ChurchDomainHealthCycle;
use App\Domain\ChurchDomainLifecycle;
use App\Domain\Dns\DnsAddressResult;
use App\Domain\Dns\DnsLookupResult;
use App\Domain\Dns\DnsLookupStatus;
use App\Domain\Dns\DnsResolver;
use App\Domain\Dns\FakeDnsResolver;
use App\Domain\HealthOutcome;
use App\Domain\Provisioning\DomainProvisioner;
use App\Domain\Provisioning\FakeDomainProvisioner;
use App\Domain\Provisioning\ProvisioningStatus;
use App\Domain\RequestChurchCustomDomain;
use App\Enums\ChurchRole;
use App\Enums\DomainFailureCode;
use App\Enums\DomainStatus;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ChurchDomainHealthCycleTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $verificationTokens = [];

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

    public function test_all_checks_healthy_clears_prior_partial_failure_state_and_preserves_verification_timestamps(): void
    {
        $domain = $this->activeDomain('church-a.org');
        $domain->forceFill([
            'consecutive_failures' => 2,
            'failure_streak_started_at' => now()->subHours(3),
            'failure_code' => DomainFailureCode::DnsMismatch->value,
        ])->save();
        $originalOwnershipVerifiedAt = $domain->ownership_verified_at;
        $originalRoutingVerifiedAt = $domain->routing_verified_at;
        $originalTlsReadyAt = $domain->tls_ready_at;

        $this->bindFullyHealthyDns('church-a.org', $domain);
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));

        $result = app(ChurchDomainHealthCycle::class)->run($domain->fresh(), 'cycle-1');

        $this->assertSame(HealthOutcome::Healthy, $result->outcome);
        $fresh = $domain->fresh();
        $this->assertSame(DomainStatus::Active, $fresh->status);
        $this->assertSame(0, $fresh->consecutive_failures);
        $this->assertNull($fresh->failure_streak_started_at);
        $this->assertNull($fresh->failure_code);
        $this->assertNotNull($fresh->last_checked_at);
        $this->assertTrue($fresh->ownership_verified_at->equalTo($originalOwnershipVerifiedAt));
        $this->assertTrue($fresh->routing_verified_at->equalTo($originalRoutingVerifiedAt));
        $this->assertTrue($fresh->tls_ready_at->equalTo($originalTlsReadyAt));
    }

    public function test_ownership_healthy_routing_unhealthy_increments_failure_once(): void
    {
        $domain = $this->activeDomain('church-b.org');
        $dns = (new FakeDnsResolver)->setTxt('_keryon-verification.church-b.org', [$this->verificationTokenFor($domain)]);
        // No cname/addresses configured => routing check finds nothing (NotFound).
        $this->app->instance(DnsResolver::class, $dns);
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));

        $result = app(ChurchDomainHealthCycle::class)->run($domain->fresh(), 'cycle-2');

        $this->assertSame(HealthOutcome::ConfirmedUnhealthy, $result->outcome);
        $this->assertSame(DomainFailureCode::DnsNotConfigured, $result->failureCode);
        $fresh = $domain->fresh();
        $this->assertSame(1, $fresh->consecutive_failures);
        $this->assertNotNull($fresh->failure_streak_started_at);
        $this->assertSame(1, DB::table('church_domain_events')->where('church_domain_id', $fresh->id)->where('event_type', 'health_check_failed')->count());
    }

    public function test_ownership_unhealthy_routing_unhealthy_increments_once_using_ownership_priority(): void
    {
        $domain = $this->activeDomain('church-c.org');
        // Wrong TXT value AND no routing evidence: both confirmed unhealthy.
        $dns = (new FakeDnsResolver)->setTxt('_keryon-verification.church-c.org', ['wrong-token']);
        $this->app->instance(DnsResolver::class, $dns);
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));

        $result = app(ChurchDomainHealthCycle::class)->run($domain->fresh(), 'cycle-3');

        $this->assertSame(HealthOutcome::ConfirmedUnhealthy, $result->outcome);
        // Ownership is checked first — its failure code is "most relevant"
        // (a wrong TXT value is Found-but-mismatched, so DnsMismatch).
        $this->assertSame(DomainFailureCode::DnsMismatch, $result->failureCode);
        $this->assertSame(1, $domain->fresh()->consecutive_failures);
    }

    public function test_ownership_and_routing_healthy_tls_failed_increments_once(): void
    {
        $domain = $this->activeDomain('church-d.org');
        $this->bindFullyHealthyDns('church-d.org', $domain);
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Failed));

        $result = app(ChurchDomainHealthCycle::class)->run($domain->fresh(), 'cycle-4');

        $this->assertSame(HealthOutcome::ConfirmedUnhealthy, $result->outcome);
        $this->assertSame(DomainFailureCode::CertificateFailed, $result->failureCode);
        $this->assertSame(1, $domain->fresh()->consecutive_failures);
    }

    public function test_one_healthy_subcheck_cannot_reset_an_already_failing_streak(): void
    {
        $domain = $this->activeDomain('church-e.org');
        $domain->forceFill([
            'consecutive_failures' => 2,
            'failure_streak_started_at' => now()->subHours(6),
            'failure_code' => DomainFailureCode::CertificateFailed->value,
        ])->save();
        $anchor = $domain->fresh()->failure_streak_started_at; // DB round-trip precision
        // Ownership and routing are healthy this cycle, but TLS still fails.
        $this->bindFullyHealthyDns('church-e.org', $domain);
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Failed));

        app(ChurchDomainHealthCycle::class)->run($domain->fresh(), 'cycle-5');

        $fresh = $domain->fresh();
        // Continues the existing streak (3, not reset to 1) — the healthy
        // ownership/routing sub-checks never got a chance to reset anything.
        $this->assertSame(3, $fresh->consecutive_failures);
        $this->assertTrue($fresh->failure_streak_started_at->equalTo($anchor));
    }

    public function test_dns_timeout_is_indeterminate_and_does_not_mutate_failure_state(): void
    {
        $domain = $this->activeDomain('church-f.org');
        $domain->forceFill(['consecutive_failures' => 1, 'failure_streak_started_at' => now()->subHours(2)])->save();
        $anchor = $domain->fresh()->failure_streak_started_at; // DB round-trip precision
        $dns = (new FakeDnsResolver)
            ->setCname('church-f.org', ['ingress.keryon.app'])
            ->setTxtResult('_keryon-verification.church-f.org', DnsLookupResult::timeout());
        $this->app->instance(DnsResolver::class, $dns);
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));

        $result = app(ChurchDomainHealthCycle::class)->run($domain->fresh(), 'cycle-6');

        $this->assertSame(HealthOutcome::Indeterminate, $result->outcome);
        $fresh = $domain->fresh();
        $this->assertSame(DomainStatus::Active, $fresh->status);
        $this->assertSame(1, $fresh->consecutive_failures);
        $this->assertTrue($fresh->failure_streak_started_at->equalTo($anchor));
        $this->assertNotNull($fresh->last_checked_at);
    }

    public function test_dns_resolver_unavailable_is_indeterminate_and_does_not_degrade(): void
    {
        $domain = $this->activeDomain('church-g.org');
        $dns = (new FakeDnsResolver)
            ->setCname('church-g.org', ['ingress.keryon.app'])
            ->setTxtResult('_keryon-verification.church-g.org', DnsLookupResult::unavailable());
        $this->app->instance(DnsResolver::class, $dns);
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));

        $result = app(ChurchDomainHealthCycle::class)->run($domain->fresh(), 'cycle-7');

        $this->assertSame(HealthOutcome::Indeterminate, $result->outcome);
        $fresh = $domain->fresh();
        $this->assertSame(DomainStatus::Active, $fresh->status);
        $this->assertSame(0, $fresh->consecutive_failures);
        $this->assertNull($fresh->failure_streak_started_at);
    }

    public function test_provider_api_unavailable_is_indeterminate_and_does_not_degrade(): void
    {
        $domain = $this->activeDomain('church-h.org');
        $domain->forceFill(['consecutive_failures' => 2, 'failure_streak_started_at' => now()->subHours(20)])->save();
        $this->bindFullyHealthyDns('church-h.org', $domain);
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Unavailable));

        $result = app(ChurchDomainHealthCycle::class)->run($domain->fresh(), 'cycle-8');

        $this->assertSame(HealthOutcome::Indeterminate, $result->outcome);
        $fresh = $domain->fresh();
        $this->assertSame(DomainStatus::Active, $fresh->status);
        $this->assertSame(2, $fresh->consecutive_failures); // unchanged
    }

    public function test_operational_outage_does_not_mass_degrade_existing_active_domains(): void
    {
        $domains = collect(['one', 'two', 'three'])->map(fn (string $slug): ChurchDomain => $this->activeDomain("{$slug}.org"));

        // Simulate a total Cloudflare DoH outage: every DNS lookup fails.
        $this->app->instance(DnsResolver::class, $this->totalDnsOutageResolver());
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));

        foreach ($domains as $domain) {
            $result = app(ChurchDomainHealthCycle::class)->run($domain->fresh(), (string) $domain->id);
            $this->assertSame(HealthOutcome::Indeterminate, $result->outcome);
        }

        foreach ($domains as $domain) {
            $fresh = $domain->fresh();
            $this->assertSame(DomainStatus::Active, $fresh->status, "domain {$fresh->normalized_hostname} must remain Active during a resolver outage");
            $this->assertSame(0, $fresh->consecutive_failures);
        }
    }

    private function activeDomain(string $hostname): ChurchDomain
    {
        $church = Church::create(['name' => $hostname, 'slug' => str_replace('.', '-', $hostname), 'activated_at' => now()]);
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($user);
        $claim = app(RequestChurchCustomDomain::class)->execute($church, $hostname);
        $this->verificationTokens[$claim->domain->id] = $claim->verificationToken;

        $lifecycle = app(ChurchDomainLifecycle::class);
        $domain = $lifecycle->ownershipVerified($claim->domain);
        $domain = $lifecycle->routingVerified($domain);
        $domain = $lifecycle->tlsProvisioning($domain);
        $domain = $lifecycle->tlsReady($domain);

        return $lifecycle->activate($domain);
    }

    private function verificationTokenFor(ChurchDomain $domain): string
    {
        return $this->verificationTokens[$domain->id] ?? throw new RuntimeException('No verification token recorded for this domain.');
    }

    private function bindFullyHealthyDns(string $hostname, ChurchDomain $domain): void
    {
        $dns = (new FakeDnsResolver)
            ->setTxt('_keryon-verification.'.$hostname, [$this->verificationTokenFor($domain)])
            ->setCname($hostname, ['ingress.keryon.app']);
        $this->app->instance(DnsResolver::class, $dns);
    }

    private function totalDnsOutageResolver(): DnsResolver
    {
        return new class implements DnsResolver
        {
            public function txt(string $hostname): DnsLookupResult
            {
                return DnsLookupResult::unavailable();
            }

            public function cname(string $hostname): DnsLookupResult
            {
                return DnsLookupResult::unavailable();
            }

            public function addresses(string $hostname): DnsAddressResult
            {
                return new DnsAddressResult(DnsLookupStatus::Unavailable);
            }
        };
    }
}
