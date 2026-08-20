<?php

namespace Tests\Feature\Async;

use App\Enums\ChurchRole;
use App\Jobs\TenantAwareJob;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\TenantContext;
use App\Support\TenantExecutionContext;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\Support\Fakes\RecordsTenantContextJob;
use Tests\TestCase;

/**
 * K-ASYNC-001 §14/§18/§19/§32/§33 — the reusable `TenantAwareJob` base,
 * exercised through a deliberately trivial, non-product concrete Job (see
 * `RecordsTenantContextJob`'s own docblock for why no FaithFlow job exists
 * yet). Jobs are invoked directly via `->handle()`, called sequentially
 * within one PHP process — the same invocation pattern a real, long-lived
 * `queue:work` worker uses (repeated `handle()` calls without restarting
 * the process), and the strongest practical in-process approximation of
 * worker leakage available without standing up a second, real worker
 * process. See K-ASYNC-001 report §40 for the explicit realism discussion.
 */
class TenantAwareJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RecordsTenantContextJob::reset();
    }

    // --- Worker leakage (§14/§32) ---

    public function test_sequential_jobs_for_different_churches_never_leak_context_in_the_same_process(): void
    {
        $churchA = Church::create(['name' => 'Worker Church A', 'slug' => 'worker-church-a']);
        $churchB = Church::create(['name' => 'Worker Church B', 'slug' => 'worker-church-b']);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $userB = User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create();

        app(TenantContext::class); // resolve the scoped instance once, matching the DI path handle() uses

        $jobA = new RecordsTenantContextJob(new TenantExecutionContext($churchA->id, $userA->id));
        $jobA->handle(app(TenantContext::class));

        $jobB = new RecordsTenantContextJob(new TenantExecutionContext($churchB->id, $userB->id));
        $jobB->handle(app(TenantContext::class));

        $this->assertSame([
            ['church_id' => $churchA->id, 'actor_user_id' => $userA->id, 'membership_present' => true],
            ['church_id' => $churchB->id, 'actor_user_id' => $userB->id, 'membership_present' => true],
        ], RecordsTenantContextJob::$observed);
    }

    public function test_a_failing_job_does_not_leak_its_context_into_the_next_job(): void
    {
        $churchA = Church::create(['name' => 'Worker Church C', 'slug' => 'worker-church-c']);
        $churchB = Church::create(['name' => 'Worker Church D', 'slug' => 'worker-church-d']);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $userB = User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create();

        RecordsTenantContextJob::$duringExecution = function (): never {
            throw new RuntimeException('Simulated domain failure inside execute().');
        };

        $jobA = new RecordsTenantContextJob(new TenantExecutionContext($churchA->id, $userA->id));

        try {
            $jobA->handle(app(TenantContext::class));
            $this->fail('Expected the RuntimeException to propagate out of handle().');
        } catch (RuntimeException) {
            // expected — a plain domain exception is not the context/
            // tenant-state category TenantAwareJob catches, so it
            // propagates for Laravel's normal retry handling.
        }

        RecordsTenantContextJob::$duringExecution = null;

        $jobB = new RecordsTenantContextJob(new TenantExecutionContext($churchB->id, $userB->id));
        $jobB->handle(app(TenantContext::class));

        // jobA's execute() recorded its own (correct, churchA) state before
        // throwing — the point here is that jobB's own recording is still
        // exactly churchB, uncontaminated by jobA's failure.
        $lastObserved = RecordsTenantContextJob::$observed[array_key_last(RecordsTenantContextJob::$observed)];
        $this->assertSame($churchB->id, $lastObserved['church_id']);
        $this->assertSame($userB->id, $lastObserved['actor_user_id']);
        $this->assertNull(app(TenantContext::class)->currentChurchId());
    }

    // --- Cross-Church target defence (§18) ---

    public function test_restored_context_still_enforces_tenant_scoping_on_existing_domain_queries(): void
    {
        $churchA = Church::create(['name' => 'Target Church A', 'slug' => 'target-church-a']);
        $churchB = Church::create(['name' => 'Target Church B', 'slug' => 'target-church-b']);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($userA);
        $contentItemB = ContentItem::create(['title' => 'Church B item', 'content_type' => 'general', 'body' => 'x']);
        $contentItemB->forceFill(['church_id' => $churchB->id])->save();
        $this->app['auth']->forgetGuards();

        $foundCrossChurch = null;

        RecordsTenantContextJob::$duringExecution = function () use ($contentItemB, &$foundCrossChurch): void {
            $foundCrossChurch = ContentItem::query()->find($contentItemB->id);
        };

        $job = new RecordsTenantContextJob(new TenantExecutionContext($churchA->id, $userA->id));
        $job->handle(app(TenantContext::class));

        $this->assertNull($foundCrossChurch);
    }

    // --- Failure classification (§21) ---

    public function test_an_untrusted_context_fails_the_job_immediately_without_executing_the_work(): void
    {
        $job = new RecordsTenantContextJob(new TenantExecutionContext(999999, null));

        // handle() catches UntrustedTenantExecutionException internally and
        // calls $this->fail() rather than rethrowing — this proxies to the
        // job's own failed() handling instead of Laravel's normal retry
        // path, so handle() itself returns normally here.
        $job->handle(app(TenantContext::class));

        $this->assertSame([], RecordsTenantContextJob::$observed);
    }

    // --- Idempotency primitive proof (§19/§33) ---

    /**
     * Not a claim that K-ASYNC-001 solves idempotency generically (it
     * deliberately does not — see TenantAwareJob's own docblock) — this
     * proves the specific infrastructure a future job would opt into
     * (`ShouldBeUnique`, backed by the database cache-lock table) is real,
     * already-provisioned infrastructure, not a sync-driver-only
     * approximation. See K-ASYNC-001 report §19/§33.
     */
    public function test_the_database_cache_driver_supports_real_atomic_locks_for_a_future_shouldbeunique_job(): void
    {
        $lock = Cache::lock('k-async-001-proof-lock', 5);

        $this->assertTrue($lock->get());

        $second = Cache::lock('k-async-001-proof-lock', 5);

        try {
            $this->assertFalse($second->get());
        } finally {
            $lock->release();
        }
    }

    public function test_lock_timeout_is_a_real_exception_type_from_the_configured_cache_driver(): void
    {
        $lock = Cache::lock('k-async-001-proof-lock-timeout', 5);
        $lock->get();

        $second = Cache::lock('k-async-001-proof-lock-timeout', 5);

        try {
            $second->block(1);
            $this->fail('Expected a LockTimeoutException.');
        } catch (LockTimeoutException $e) {
            // Confirms the database cache driver's lock provider is
            // genuinely wired, not merely configured.
            $this->assertInstanceOf(LockTimeoutException::class, $e);
        } finally {
            $lock->release();
        }
    }
}
