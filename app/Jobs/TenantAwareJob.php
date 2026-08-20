<?php

namespace App\Jobs;

use App\Support\Exceptions\UntrustedTenantExecutionException;
use App\Support\TenantContext;
use App\Support\TenantExecutionContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The reusable tenant-aware background-execution seam — see K-ASYNC-001.
 * Not FaithFlow-specific: any future tenant-owned background operation
 * (FaithFlow generation, publishing, Media Library processing, Design
 * Studio rendering, imports, ...) extends this and implements `execute()`
 * only. Everything below is deliberately generic.
 *
 * Contract for subclasses:
 * - Construct with a `TenantExecutionContext` captured at dispatch time
 *   (`TenantContext::capture()`, or built directly with `actorUserId: null`
 *   for system-originated work — see K-ASYNC-001 §9).
 * - Implement `execute()`. Inside it, `app(TenantContext::class)` (or any
 *   existing Policy/Action/`BelongsToChurch`-scoped query) behaves exactly
 *   as it does today under an authenticated HTTP request — the restored
 *   Church (and, when present, ChurchMembership) is active for the
 *   duration of `execute()` only.
 * - `$tries`/`backoff()` below are sane platform defaults, not a universal
 *   rule — override them per job when a specific operation's risk profile
 *   warrants it (§20).
 * - A context/security/tenant-state failure (`UntrustedTenantExecutionException`
 *   — Church inactive/missing, or the actor's membership revoked between
 *   dispatch and execution) is never retried: retrying with the same
 *   context would deterministically fail again, so `handle()` fails the
 *   job immediately instead of consuming an attempt (§21). Every other
 *   exception from `execute()` propagates normally, so Laravel's own
 *   retry/backoff/failed-job handling applies unchanged — domain-level
 *   failure classification (transient provider error vs. permanent
 *   "target deleted") is each future job's own responsibility, not
 *   something this base class can know generically.
 * - Idempotency is deliberately not solved generically here (§19) — rely
 *   on the underlying domain Action already being idempotent (as every
 *   FaithFlow Action already is), or opt a specific job into Laravel's
 *   native `ShouldBeUnique` (backed by the already-available database
 *   cache-lock table — see K-ASYNC-001 report §19) when the operation
 *   itself is not naturally idempotent.
 */
abstract class TenantAwareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly TenantExecutionContext $context) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    final public function handle(TenantContext $tenantContext): void
    {
        Context::add([
            'job' => static::class,
            'church_id' => $this->context->churchId,
            'actor_user_id' => $this->context->actorUserId,
            'attempt' => $this->attempts(),
        ]);

        try {
            $tenantContext->runFor($this->context, fn () => $this->execute());
        } catch (UntrustedTenantExecutionException $e) {
            // See K-ASYNC-001 §11/§12/§21 — the Logging_Standard-compliant
            // fields only: identifiers and a failure category, never full
            // domain content.
            Log::warning('Background job rejected — untrusted tenant execution context.', [
                'job' => static::class,
                'church_id' => $this->context->churchId,
                'actor_user_id' => $this->context->actorUserId,
                'failure_category' => 'context',
                'exception' => $e::class,
            ]);

            $this->fail($e);
        } finally {
            Context::forget(['job', 'church_id', 'actor_user_id', 'attempt']);
        }
    }

    /**
     * The actual unit of tenant-owned work. Runs with a restored
     * TenantContext active — see the class docblock above.
     */
    abstract protected function execute(): void;

    public function failed(Throwable $exception): void
    {
        Log::error('Background job failed.', [
            'job' => static::class,
            'church_id' => $this->context->churchId,
            'actor_user_id' => $this->context->actorUserId,
            'exception' => $exception::class,
        ]);
    }
}
