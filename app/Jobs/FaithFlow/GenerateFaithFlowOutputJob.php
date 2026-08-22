<?php

namespace App\Jobs\FaithFlow;

use App\FaithFlow\Actions\GenerateFaithFlowOutput;
use App\Jobs\TenantAwareJob;
use App\Models\FaithFlowOutput;
use App\Support\TenantExecutionContext;

/**
 * K-FAITHFLOW-001F §15/§16/§17 — one output type per job, so one output's
 * failure never blocks or delays the others (independently recoverable
 * units, per the directive's explicit instruction not to build one giant
 * FaithFlow job). Wraps the existing, unmodified `GenerateFaithFlowOutput`
 * Action only — no business logic here.
 */
class GenerateFaithFlowOutputJob extends TenantAwareJob
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

        app(GenerateFaithFlowOutput::class)->handle($output);
    }
}
