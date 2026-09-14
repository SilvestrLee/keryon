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
use App\Enums\DomainTlsStatus;
use App\Jobs\PollChurchDomainTls;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * K-DOMAIN-001E-R1 — TLS poll due-time governance. Proves
 * tls_poll_interval_minutes actually gates the due-domain query, and that
 * last_checked_at only moves when a real provider check occurred.
 */
class ChurchDomainTlsPollDueTimeTest extends TestCase
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

    public function test_recently_polled_provisioning_domain_is_not_dispatched(): void
    {
        Queue::fake();
        config()->set('public-website.custom_domains.tls_poll_interval_minutes', 5);

        $domain = $this->provisioningDomain('recent.org');
        $domain->forceFill(['last_checked_at' => now()->subMinutes(2)])->save();

        $this->artisan('domains:dispatch-tls-polls')->assertSuccessful();

        Queue::assertNotPushed(PollChurchDomainTls::class);
    }

    public function test_provisioning_domain_older_than_configured_interval_is_dispatched(): void
    {
        Queue::fake();
        config()->set('public-website.custom_domains.tls_poll_interval_minutes', 5);

        $domain = $this->provisioningDomain('stale.org');
        $domain->forceFill(['last_checked_at' => now()->subMinutes(6)])->save();

        $this->artisan('domains:dispatch-tls-polls')->assertSuccessful();

        Queue::assertPushed(PollChurchDomainTls::class, fn (PollChurchDomainTls $job) => $job->domainId === $domain->id);
    }

    public function test_provisioning_domain_with_null_last_checked_at_is_dispatched(): void
    {
        Queue::fake();

        // ownershipVerified()/routingVerified() set last_checked_at as part
        // of their own semantics — force it back to null to exercise the
        // "never actually TLS-checked" case explicitly.
        $domain = $this->provisioningDomain('never-polled.org');
        $domain->forceFill(['last_checked_at' => null])->save();
        $this->assertNull($domain->fresh()->last_checked_at);

        $this->artisan('domains:dispatch-tls-polls')->assertSuccessful();

        Queue::assertPushed(PollChurchDomainTls::class, fn (PollChurchDomainTls $job) => $job->domainId === $domain->id);
    }

    public function test_changing_the_interval_changes_due_selection(): void
    {
        Queue::fake();
        $domain = $this->provisioningDomain('boundary.org');
        $domain->forceFill(['last_checked_at' => now()->subMinutes(8)])->save();

        config()->set('public-website.custom_domains.tls_poll_interval_minutes', 10);
        $this->artisan('domains:dispatch-tls-polls')->assertSuccessful();
        Queue::assertNotPushed(PollChurchDomainTls::class, fn (PollChurchDomainTls $job) => $job->domainId === $domain->id);

        config()->set('public-website.custom_domains.tls_poll_interval_minutes', 5);
        $this->artisan('domains:dispatch-tls-polls')->assertSuccessful();
        Queue::assertPushed(PollChurchDomainTls::class, fn (PollChurchDomainTls $job) => $job->domainId === $domain->id);
    }

    public function test_a_zero_or_negative_interval_is_clamped_to_a_minimum_of_one_minute(): void
    {
        Queue::fake();
        config()->set('public-website.custom_domains.tls_poll_interval_minutes', 0);

        $domain = $this->provisioningDomain('clamped.org');
        // Just checked — under a true zero/negative interval this would be
        // immediately due again ("continuous polling"); clamped to 1 minute
        // it must not be.
        $domain->forceFill(['last_checked_at' => now()])->save();

        $this->artisan('domains:dispatch-tls-polls')->assertSuccessful();

        Queue::assertNotPushed(PollChurchDomainTls::class, fn (PollChurchDomainTls $job) => $job->domainId === $domain->id);
    }

    public function test_pending_provider_response_updates_last_checked_at(): void
    {
        $domain = $this->provisioningDomain('pending.org');
        $domain->forceFill(['last_checked_at' => now()->subHour()])->save();
        $before = $domain->fresh()->last_checked_at;
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Pending));

        $this->app->call([new PollChurchDomainTls($domain->id), 'handle']);

        $fresh = $domain->fresh();
        $this->assertSame(DomainTlsStatus::Provisioning, $fresh->tls_status);
        $this->assertFalse($fresh->last_checked_at->equalTo($before), 'last_checked_at must move after a real Pending check');
    }

    public function test_unavailable_provider_response_updates_last_checked_at_but_leaves_tls_and_failure_streak_untouched(): void
    {
        $domain = $this->provisioningDomain('unavailable.org');
        $domain->forceFill([
            'last_checked_at' => now()->subHour(),
            'consecutive_failures' => 0,
            'failure_streak_started_at' => null,
        ])->save();
        $before = $domain->fresh()->last_checked_at;
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Unavailable));

        $this->app->call([new PollChurchDomainTls($domain->id), 'handle']);

        $fresh = $domain->fresh();
        $this->assertFalse($fresh->last_checked_at->equalTo($before), 'last_checked_at must move after a real Unavailable check');
        $this->assertSame(DomainTlsStatus::Provisioning, $fresh->tls_status, 'must remain Provisioning, not fail terminally');
        $this->assertNotSame(DomainFailureCode::CertificateFailed->value, $fresh->failure_code);
        $this->assertSame(0, $fresh->consecutive_failures, 'must not start/advance a confirmed-failure streak');
        $this->assertNull($fresh->failure_streak_started_at);
    }

    public function test_no_op_returns_do_not_move_last_checked_at(): void
    {
        // Disabled domains hit the early no-op return — checkTlsStatus() is
        // never called, so last_checked_at must not move.
        $domain = $this->provisioningDomain('noop.org');
        $domain->forceFill(['disabled_at' => now(), 'last_checked_at' => now()->subHour()])->save();
        $before = $domain->fresh()->last_checked_at;
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));

        $this->app->call([new PollChurchDomainTls($domain->id), 'handle']);

        $this->assertTrue($domain->fresh()->last_checked_at->equalTo($before));
    }

    public function test_rate_limited_job_does_not_move_last_checked_at(): void
    {
        config()->set('public-website.custom_domains.provider_rate_limit_per_minute', 1);
        $domain = $this->provisioningDomain('rate-limited.org');
        $domain->forceFill(['last_checked_at' => now()->subHour()])->save();
        $before = $domain->fresh()->last_checked_at;
        $this->app->instance(DomainProvisioner::class, (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready));

        $middleware = new RateLimited('domain-provider');
        $job = new PollChurchDomainTls($domain->id);
        $released = false;
        $stubJob = new class($released)
        {
            public function __construct(private bool &$released) {}

            public function release($delay = 0): void
            {
                $this->released = true;
            }
        };

        // Consume the one allowed slot for this minute.
        $middleware->handle($stubJob, fn () => null);
        // Second attempt within the same window must be released, never
        // reaching handle() — checkTlsStatus() is never called.
        $ran = false;
        $middleware->handle($stubJob, function () use (&$ran, $job): void {
            $ran = true;
            $this->app->call([$job, 'handle']);
        });

        $this->assertFalse($ran, 'the job body must never run once rate-limited');
        $this->assertTrue($domain->fresh()->last_checked_at->equalTo($before));
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
