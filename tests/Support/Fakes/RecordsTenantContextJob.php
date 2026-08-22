<?php

namespace Tests\Support\Fakes;

use App\Jobs\TenantAwareJob;
use App\Support\TenantContext;
use Closure;

/**
 * A deliberately trivial, non-product concrete TenantAwareJob — exists only
 * to exercise the abstract base class's real lifecycle (restore → execute
 * → clear, worker-leakage safety, cross-church defense) against something
 * dispatchable, without wiring any real domain (FaithFlow, Content Studio,
 * etc.) into K-ASYNC-001 — see the directive's explicit "do not queue
 * FaithFlow yet" instruction.
 */
class RecordsTenantContextJob extends TenantAwareJob
{
    /** @var list<array{church_id: ?int, actor_user_id: ?int, membership_present: bool}> */
    public static array $observed = [];

    public static ?Closure $duringExecution = null;

    public static function reset(): void
    {
        static::$observed = [];
        static::$duringExecution = null;
    }

    protected function execute(): void
    {
        $tenantContext = app(TenantContext::class);

        static::$observed[] = [
            'church_id' => $tenantContext->currentChurchId(),
            'actor_user_id' => $tenantContext->currentMembership()?->user_id,
            'membership_present' => $tenantContext->currentMembership() !== null,
        ];

        if (static::$duringExecution !== null) {
            (static::$duringExecution)();
        }
    }
}
