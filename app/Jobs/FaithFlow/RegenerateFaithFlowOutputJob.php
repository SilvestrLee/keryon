<?php

namespace App\Jobs\FaithFlow;

use App\FaithFlow\Actions\RegenerateFaithFlowOutput;
use App\Jobs\TenantAwareJob;
use App\Models\FaithFlowOutput;
use App\Support\TenantExecutionContext;

/**
 * K-FAITHFLOW-001F §15/§16/§17/§33 — the async wrapper around the existing,
 * unmodified `RegenerateFaithFlowOutput` Action. That Action already
 * preserves human-edited `content` on both success and failure (K-FAITHFLOW-
 * 001D §61/§28) — this job changes none of that behavior, it only moves the
 * provider call off the request/response cycle.
 */
class RegenerateFaithFlowOutputJob extends TenantAwareJob
{
    public function __construct(
        TenantExecutionContext $context,
        public readonly int $outputId,
    ) {
        parent::__construct($context);
    }

    protected function execute(): void
    {
        $output = FaithFlowOutput::query()->findOrFail($this->outputId);

        app(RegenerateFaithFlowOutput::class)->handle($output);
    }
}
