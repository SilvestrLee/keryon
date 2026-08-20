<?php

namespace App\FaithFlow\Actions;

use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowRunStatus;
use App\FaithFlow\Actions\Concerns\GeneratesFaithFlowOutputContent;
use App\FaithFlow\FaithFlowAi;
use App\Models\FaithFlowOutput;
use LogicException;

/**
 * First-time generation for one FaithFlowOutput — see K-FAITHFLOW-001D §11.
 * Authorization is deliberately not performed here — the caller's
 * responsibility, mirroring AnalyzeFaithFlowSource's own precedent
 * (K-FAITHFLOW-001C §16) which itself mirrors ContentItem's established
 * pattern.
 */
class GenerateFaithFlowOutput
{
    use GeneratesFaithFlowOutputContent;

    public function __construct(private readonly FaithFlowAi $ai) {}

    public function handle(FaithFlowOutput $output): FaithFlowOutput
    {
        if (in_array($output->status, [FaithFlowOutputStatus::GENERATED, FaithFlowOutputStatus::APPROVED], true)) {
            // Idempotent — §31: avoid unnecessary provider spend once an
            // output already has valid content. Explicit regeneration is a
            // distinct, separately-invoked intention (RegenerateFaithFlowOutput).
            return $output;
        }

        if ($output->status === FaithFlowOutputStatus::GENERATING) {
            throw new LogicException('This output is already being generated.');
        }

        $run = $output->run;

        if ($run->status !== FaithFlowRunStatus::ANALYZED || $run->canonical_analysis === null) {
            throw new LogicException('This run has no valid canonical analysis to generate from.');
        }

        // The concurrency guard (§32) — an atomic conditional update, no new
        // infrastructure. If another request already claimed this output
        // (0 rows affected), just observe whatever state it's in now rather
        // than erroring on a legitimate race outcome.
        $claimed = FaithFlowOutput::query()
            ->whereKey($output->id)
            ->whereIn('status', [FaithFlowOutputStatus::PENDING->value, FaithFlowOutputStatus::FAILED->value])
            ->update(['status' => FaithFlowOutputStatus::GENERATING->value]);

        if ($claimed === 0) {
            return $output->fresh();
        }

        return $this->performGeneration($output->fresh(), $this->ai, isRegeneration: false);
    }
}
