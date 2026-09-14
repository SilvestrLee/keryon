<?php

namespace Tests\Feature\Domains;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Domain\ChurchDomainHealthCycle;
use App\Domain\ChurchDomainLifecycle;
use App\Domain\Dns\DnsLookupResult;
use App\Domain\Dns\DnsResolver;
use App\Domain\Dns\FakeDnsResolver;
use App\Domain\HealthOutcome;
use App\Domain\MakeChurchDomainPrimary;
use App\Domain\Provisioning\DomainProvisioner;
use App\Domain\Provisioning\FakeDomainProvisioner;
use App\Domain\Provisioning\ProvisioningStatus;
use App\Domain\RequestChurchCustomDomain;
use App\Enums\ChurchDomainEventType;
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

class ChurchDomainDegradationAndRecoveryTest extends TestCase
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

    public function test_three_failures_under_24_hours_do_not_degrade(): void
    {
        $this->travelTo($t0 = now());
        $domain = $this->activeDomain('under-24h.org');

        $this->runUnhealthyCycle($domain, 'church-a-1'); // T+0
        $this->travelTo($t0->copy()->addHours(12));
        $this->runUnhealthyCycle($domain, 'church-a-2'); // T+12h
        $this->travelTo($t0->copy()->addHours(20));
        $this->runUnhealthyCycle($domain, 'church-a-3'); // T+20h — under 24h

        $fresh = $domain->fresh();
        $this->assertSame(3, $fresh->consecutive_failures);
        $this->assertSame(DomainStatus::Active, $fresh->status, 'must not degrade before the 24-hour window elapses');
    }

    public function test_three_failures_spanning_24_hours_degrade(): void
    {
        $this->travelTo($t0 = now());
        $domain = $this->activeDomain('spans-24h.org');

        $this->runUnhealthyCycle($domain, 'church-b-1'); // T+0 — starts the streak
        $this->travelTo($t0->copy()->addHours(12));
        $this->runUnhealthyCycle($domain, 'church-b-2'); // T+12h
        $this->travelTo($t0->copy()->addHours(24));
        $this->runUnhealthyCycle($domain, 'church-b-3'); // T+24h — eligible

        $fresh = $domain->fresh();
        $this->assertSame(3, $fresh->consecutive_failures);
        $this->assertSame(DomainStatus::Degraded, $fresh->status);
        $this->assertFalse((bool) $fresh->is_primary);
        $this->assertSame(1, DB::table('church_domain_events')->where('church_domain_id', $fresh->id)->where('event_type', ChurchDomainEventType::Degraded->value)->count());
    }

    public function test_last_checked_at_moves_every_check_while_failure_streak_started_at_stays_anchored(): void
    {
        $this->travelTo($t0 = now());
        $domain = $this->activeDomain('anchored.org');

        $this->runUnhealthyCycle($domain, 'church-c-1');
        $firstChecked = $domain->fresh()->last_checked_at;
        $streakStart = $domain->fresh()->failure_streak_started_at;

        $this->travelTo($t0->copy()->addHours(6));
        $this->runUnhealthyCycle($domain, 'church-c-2');
        $secondChecked = $domain->fresh()->last_checked_at;

        $this->assertFalse($firstChecked->equalTo($secondChecked), 'last_checked_at must move on every check');
        $this->assertTrue($domain->fresh()->failure_streak_started_at->equalTo($streakStart), 'failure_streak_started_at must stay anchored to the first confirmed failure');
    }

    public function test_indeterminate_cycle_does_not_increment_and_does_not_reset_existing_streak(): void
    {
        $domain = $this->activeDomain('indeterminate.org');
        $this->runUnhealthyCycle($domain, 'church-d-1');
        $streakStart = $domain->fresh()->failure_streak_started_at;
        $failures = $domain->fresh()->consecutive_failures;

        // Now an indeterminate cycle: only ownership is unavailable — routing
        // is stubbed healthy so it can't also contribute a confirmed failure.
        $this->app->instance(DnsResolver::class, (new FakeDnsResolver)
            ->setCname('indeterminate.org', ['ingress.keryon.app'])
            ->setTxtResult('_keryon-verification.indeterminate.org', DnsLookupResult::unavailable()));
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Unavailable));
        $result = app(ChurchDomainHealthCycle::class)->run($domain->fresh(), 'church-d-2');

        $this->assertSame(HealthOutcome::Indeterminate, $result->outcome);
        $fresh = $domain->fresh();
        $this->assertSame($failures, $fresh->consecutive_failures);
        $this->assertTrue($fresh->failure_streak_started_at->equalTo($streakStart));
    }

    public function test_successful_cycle_resets_an_existing_failure_streak(): void
    {
        $domain = $this->activeDomain('resets.org');
        $this->runUnhealthyCycle($domain, 'church-e-1');
        $this->assertSame(1, $domain->fresh()->consecutive_failures);

        $this->bindFullyHealthyDns('resets.org', $domain);
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));
        $result = app(ChurchDomainHealthCycle::class)->run($domain->fresh(), 'church-e-2');

        $this->assertSame(HealthOutcome::Healthy, $result->outcome);
        $fresh = $domain->fresh();
        $this->assertSame(0, $fresh->consecutive_failures);
        $this->assertNull($fresh->failure_streak_started_at);
        $this->assertNull($fresh->failure_code);
    }

    public function test_degraded_domain_recovers_to_active_on_a_fully_healthy_cycle(): void
    {
        $this->travelTo($t0 = now());
        $domain = $this->activeDomain('recovers.org');
        $this->runUnhealthyCycle($domain, 'church-f-1');
        $this->travelTo($t0->copy()->addHours(12));
        $this->runUnhealthyCycle($domain, 'church-f-2');
        $this->travelTo($t0->copy()->addHours(24));
        $this->runUnhealthyCycle($domain, 'church-f-3');
        $this->assertSame(DomainStatus::Degraded, $domain->fresh()->status);

        $this->bindFullyHealthyDns('recovers.org', $domain);
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));
        $result = app(ChurchDomainHealthCycle::class)->run($domain->fresh(), 'church-f-4');

        $this->assertSame(HealthOutcome::Healthy, $result->outcome);
        $fresh = $domain->fresh();
        $this->assertSame(DomainStatus::Active, $fresh->status);
        $this->assertSame(0, $fresh->consecutive_failures);
        $this->assertNull($fresh->failure_streak_started_at);
        $this->assertNull($fresh->failure_code);
        $this->assertSame(1, DB::table('church_domain_events')->where('church_domain_id', $fresh->id)->where('event_type', ChurchDomainEventType::Recovered->value)->count());
    }

    public function test_recovery_does_not_automatically_restore_primary(): void
    {
        $this->travelTo($t0 = now());
        $domain = $this->activeDomain('primary-recovers.org');
        app(MakeChurchDomainPrimary::class)->execute($domain->fresh());
        $this->assertTrue((bool) $domain->fresh()->is_primary);

        $this->runUnhealthyCycle($domain, 'church-g-1');
        $this->travelTo($t0->copy()->addHours(12));
        $this->runUnhealthyCycle($domain, 'church-g-2');
        $this->travelTo($t0->copy()->addHours(24));
        $this->runUnhealthyCycle($domain, 'church-g-3');
        $this->assertSame(DomainStatus::Degraded, $domain->fresh()->status);
        $this->assertFalse((bool) $domain->fresh()->is_primary);

        $this->bindFullyHealthyDns('primary-recovers.org', $domain);
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));
        app(ChurchDomainHealthCycle::class)->run($domain->fresh(), 'church-g-4');

        $fresh = $domain->fresh();
        $this->assertSame(DomainStatus::Active, $fresh->status);
        $this->assertFalse((bool) $fresh->is_primary, 'recovery must never automatically restore is_primary');
    }

    /**
     * Confirmed-unhealthy cycle: correct TXT (so ownership stays healthy —
     * isolating routing as the confirmed failure) with no CNAME/address
     * evidence configured.
     */
    private function runUnhealthyCycle(ChurchDomain $domain, string $correlationId): void
    {
        $dns = (new FakeDnsResolver)->setTxt('_keryon-verification.'.$domain->normalized_hostname, [$this->verificationTokenFor($domain)]);
        $this->app->instance(DnsResolver::class, $dns);
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));

        $result = app(ChurchDomainHealthCycle::class)->run($domain->fresh(), $correlationId);

        if ($result->outcome !== HealthOutcome::ConfirmedUnhealthy || $result->failureCode !== DomainFailureCode::DnsNotConfigured) {
            throw new RuntimeException('Test fixture did not produce the expected confirmed-unhealthy cycle.');
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
}
