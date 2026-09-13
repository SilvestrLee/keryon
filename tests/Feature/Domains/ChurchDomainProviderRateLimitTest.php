<?php

namespace Tests\Feature\Domains;

use App\Domain\ChurchDomainLifecycle;
use App\Domain\RequestChurchCustomDomain;
use App\Enums\ChurchRole;
use App\Jobs\CheckChurchDomainHealth;
use App\Jobs\PollChurchDomainTls;
use App\Jobs\VerifyChurchDomain;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ChurchDomainProviderRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_domain_provider_limiter_is_registered_with_the_configured_rate(): void
    {
        config()->set('public-website.custom_domains.provider_rate_limit_per_minute', 7);

        $limit = RateLimiter::limiter('domain-provider')(new class {});

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(7, $limit->maxAttempts);
    }

    public function test_provider_consuming_jobs_declare_the_domain_provider_rate_limit_middleware(): void
    {
        $verify = new VerifyChurchDomain(1);
        $health = new CheckChurchDomainHealth(1);
        $poll = new PollChurchDomainTls(1);

        foreach ([$verify, $health, $poll] as $job) {
            $middleware = $job->middleware();
            $this->assertCount(1, $middleware);
            $this->assertInstanceOf(RateLimited::class, $middleware[0]);
        }
    }

    public function test_exceeding_the_limit_releases_the_job_without_running_it_or_mutating_health_state(): void
    {
        config()->set('public-website.custom_domains.provider_rate_limit_per_minute', 1);
        config()->set('public-website.custom_domains.dns_ingress_target', 'ingress.keryon.app');

        $domain = $this->activeDomain('rate-limited.org');
        $freshBefore = $domain->fresh();

        $middleware = new RateLimited('domain-provider');
        $makeJob = function (bool &$released) {
            return new class($released)
            {
                public function __construct(private bool &$released) {}

                public function release($delay = 0): void
                {
                    $this->released = true;
                }
            };
        };

        // First call consumes the one allowed slot for this minute — same
        // limiterName/key on every call, so this genuinely exhausts it.
        $firstReleased = false;
        $firstRan = false;
        $middleware->handle($makeJob($firstReleased), function () use (&$firstRan): void {
            $firstRan = true;
        });
        $this->assertTrue($firstRan, 'the first call within the limit must run normally');
        $this->assertFalse($firstReleased);

        // Second call within the same window must be released, not run.
        $released = false;
        $ran = false;
        $middleware->handle($makeJob($released), function () use (&$ran): void {
            $ran = true;
        });

        $this->assertTrue($released, 'the job must be released, not run, once the limit is exceeded');
        $this->assertFalse($ran, 'the wrapped handler (and therefore any health-state mutation) must never run when rate-limited');

        // Confirm no health/failure-state mutation occurred as a side effect
        // of the rate-limit check itself.
        $freshAfter = $domain->fresh();
        $this->assertSame($freshBefore->consecutive_failures, $freshAfter->consecutive_failures);
        $this->assertSame($freshBefore->status, $freshAfter->status);
        $this->assertTrue($freshBefore->last_checked_at?->equalTo($freshAfter->last_checked_at) ?? $freshAfter->last_checked_at === null);
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
}
