<?php

namespace Tests\Feature\Domains;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Domain\ChurchDomainLifecycle;
use App\Domain\RequestChurchCustomDomain;
use App\Enums\ChurchRole;
use App\Enums\DomainStatus;
use App\Jobs\CheckChurchDomainHealth;
use App\Jobs\PollChurchDomainTls;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ChurchDomainSchedulingTest extends TestCase
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

    public function test_only_due_active_and_degraded_domains_dispatch_health_checks(): void
    {
        Queue::fake();

        $due = $this->activeDomain('due.org');
        $due->forceFill(['last_checked_at' => now()->subHours(13)])->save();

        $recentlyChecked = $this->activeDomain('recent.org');
        $recentlyChecked->forceFill(['last_checked_at' => now()->subHours(1)])->save();

        $neverChecked = $this->activeDomain('never-checked.org');
        $neverChecked->forceFill(['last_checked_at' => null])->save();

        $degradedDue = $this->activeDomain('degraded-due.org');
        $degradedDue->forceFill(['status' => DomainStatus::Degraded->value, 'last_checked_at' => now()->subHours(13)])->save();

        $pendingVerification = $this->pendingDomain('pending.org');

        $this->artisan('domains:dispatch-health-checks')->assertSuccessful();

        Queue::assertPushed(CheckChurchDomainHealth::class, fn (CheckChurchDomainHealth $job) => $job->domainId === $due->id);
        Queue::assertPushed(CheckChurchDomainHealth::class, fn (CheckChurchDomainHealth $job) => $job->domainId === $neverChecked->id);
        Queue::assertPushed(CheckChurchDomainHealth::class, fn (CheckChurchDomainHealth $job) => $job->domainId === $degradedDue->id);
        Queue::assertNotPushed(CheckChurchDomainHealth::class, fn (CheckChurchDomainHealth $job) => $job->domainId === $recentlyChecked->id);
        Queue::assertNotPushed(CheckChurchDomainHealth::class, fn (CheckChurchDomainHealth $job) => $job->domainId === $pendingVerification->id);
    }

    public function test_dispatch_batch_size_is_bounded(): void
    {
        Queue::fake();
        config()->set('public-website.custom_domains.dispatch_batch_size', 2);

        foreach (range(1, 5) as $i) {
            $domain = $this->activeDomain("batch-{$i}.org");
            $domain->forceFill(['last_checked_at' => now()->subDay()])->save();
        }

        $this->artisan('domains:dispatch-health-checks')->assertSuccessful();

        Queue::assertPushed(CheckChurchDomainHealth::class, 2);
    }

    public function test_provisioning_poll_candidates_dispatch_and_disabled_released_are_excluded(): void
    {
        Queue::fake();

        // Due: last_checked_at older than the configured interval.
        $provisioning = $this->provisioningDomain('poll-due.org');
        $provisioning->forceFill(['last_checked_at' => now()->subMinutes(10)])->save();

        $disabled = $this->provisioningDomain('poll-disabled.org');
        $disabled->forceFill(['disabled_at' => now(), 'last_checked_at' => now()->subMinutes(10)])->save();

        $released = $this->provisioningDomain('poll-released.org');
        $released->forceFill(['released_at' => now(), 'last_checked_at' => now()->subMinutes(10)])->save();

        $active = $this->activeDomain('poll-active.org');

        $this->artisan('domains:dispatch-tls-polls')->assertSuccessful();

        Queue::assertPushed(PollChurchDomainTls::class, fn (PollChurchDomainTls $job) => $job->domainId === $provisioning->id);
        Queue::assertNotPushed(PollChurchDomainTls::class, fn (PollChurchDomainTls $job) => $job->domainId === $disabled->id);
        Queue::assertNotPushed(PollChurchDomainTls::class, fn (PollChurchDomainTls $job) => $job->domainId === $released->id);
        Queue::assertNotPushed(PollChurchDomainTls::class, fn (PollChurchDomainTls $job) => $job->domainId === $active->id);
    }

    public function test_tls_poll_batch_size_is_bounded(): void
    {
        Queue::fake();
        config()->set('public-website.custom_domains.dispatch_batch_size', 2);

        foreach (range(1, 5) as $i) {
            $domain = $this->provisioningDomain("tls-batch-{$i}.org");
            $domain->forceFill(['last_checked_at' => now()->subMinutes(10)])->save();
        }

        $this->artisan('domains:dispatch-tls-polls')->assertSuccessful();

        Queue::assertPushed(PollChurchDomainTls::class, 2);
    }

    public function test_duplicate_health_check_jobs_are_prevented_by_uniqueness(): void
    {
        $domain = $this->activeDomain('unique-health.org');

        $first = new CheckChurchDomainHealth($domain->id);
        $second = new CheckChurchDomainHealth($domain->id);

        $this->assertSame($first->uniqueId(), $second->uniqueId());
    }

    public function test_duplicate_tls_poll_jobs_are_prevented_by_uniqueness(): void
    {
        $domain = $this->provisioningDomain('unique-poll.org');

        $first = new PollChurchDomainTls($domain->id);
        $second = new PollChurchDomainTls($domain->id);

        $this->assertSame($first->uniqueId(), $second->uniqueId());
    }

    private function activeDomain(string $hostname): ChurchDomain
    {
        $church = Church::create(['name' => $hostname, 'slug' => str_replace('.', '-', $hostname), 'activated_at' => now()]);
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($user);
        $claim = app(RequestChurchCustomDomain::class)->execute($church, $hostname);

        $lifecycle = app(ChurchDomainLifecycle::class);
        $domain = $lifecycle->ownershipVerified($claim->domain);
        $domain = $lifecycle->routingVerified($domain);
        $domain = $lifecycle->tlsProvisioning($domain);
        $domain = $lifecycle->tlsReady($domain);

        return $lifecycle->activate($domain);
    }

    private function provisioningDomain(string $hostname): ChurchDomain
    {
        $church = Church::create(['name' => $hostname, 'slug' => str_replace('.', '-', $hostname), 'activated_at' => now()]);
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($user);
        $claim = app(RequestChurchCustomDomain::class)->execute($church, $hostname);

        $lifecycle = app(ChurchDomainLifecycle::class);
        $domain = $lifecycle->ownershipVerified($claim->domain);
        $domain = $lifecycle->routingVerified($domain);

        return $lifecycle->tlsProvisioning($domain);
    }

    private function pendingDomain(string $hostname): ChurchDomain
    {
        $church = Church::create(['name' => $hostname, 'slug' => str_replace('.', '-', $hostname), 'activated_at' => now()]);
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($user);

        return app(RequestChurchCustomDomain::class)->execute($church, $hostname)->domain;
    }
}
