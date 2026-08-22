<?php

namespace App\Jobs\FaithFlow;

use App\FaithFlow\Actions\AnalyzeFaithFlowSource;
use App\Jobs\TenantAwareJob;
use App\Models\FaithFlowRun;
use App\Support\TenantExecutionContext;

/**
 * K-FAITHFLOW-001F §15/§16/§17 — the tenant-aware async wrapper around the
 * existing, unmodified `AnalyzeFaithFlowSource` domain Action. Carries only
 * a durable ID, never the run itself (K-ASYNC-001 §16/§22) — the run is
 * re-fetched inside `execute()` through the restored, tenant-scoped query,
 * exactly as `BelongsToChurch` already guarantees for every other query in
 * the app. No business logic lives here; this class is dispatch/restore
 * wiring only (K-ASYNC-001 §17).
 */
class AnalyzeFaithFlowSourceJob extends TenantAwareJob
{
    public function __construct(
        TenantExecutionContext $context,
        public readonly int $runId,
    ) {
        parent::__construct($context);
    }

    protected function execute(): void
    {
        $run = FaithFlowRun::query()->findOrFail($this->runId);

        app(AnalyzeFaithFlowSource::class)->handle($run);
    }
}
