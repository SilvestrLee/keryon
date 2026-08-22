<?php

namespace App\FaithFlow\Actions;

use App\Enums\FaithFlowOutputStatus;
use App\FaithFlow\Actions\Concerns\GeneratesFaithFlowOutputContent;
use App\FaithFlow\FaithFlowAi;
use App\Models\FaithFlowOutput;
use LogicException;

/**
 * Explicit regeneration for one FaithFlowOutput — see K-FAITHFLOW-001D §29.
 * A distinct domain intention from GenerateFaithFlowOutput (§31): valid only
 * from GENERATED (an output must already have content before it can be
 * regenerated — a FAILED first-time generation is retried via
 * GenerateFaithFlowOutput, not this class). Never happens implicitly; a
 * caller must invoke this explicitly, and it is idempotency-safe against a
 * duplicate call while already GENERATING (§29's own explicit requirement).
 */
class RegenerateFaithFlowOutput
{
    use GeneratesFaithFlowOutputContent;

    public function __construct(private readonly FaithFlowAi $ai) {}

    public function handle(FaithFlowOutput $output): FaithFlowOutput
    {
        if ($output->status === FaithFlowOutputStatus::GENERATING) {
            throw new LogicException('This output is already being generated.');
        }

        if ($output->status !== FaithFlowOutputStatus::GENERATED) {
            throw new LogicException(
                "An output with status [{$output->status->value}] cannot be regenerated — only a GENERATED output can."
            );
        }

        // The concurrency guard (§32) — same atomic conditional update as
        // GenerateFaithFlowOutput, from GENERATED specifically this time.
        $claimed = FaithFlowOutput::query()
            ->whereKey($output->id)
            ->where('status', FaithFlowOutputStatus::GENERATED->value)
            ->update(['status' => FaithFlowOutputStatus::GENERATING->value]);

        if ($claimed === 0) {
            return $output->fresh();
        }

        $output = $output->fresh();
        $output->increment('regeneration_count');

        return $this->performGeneration($output->fresh(), $this->ai, isRegeneration: true);
    }
}
