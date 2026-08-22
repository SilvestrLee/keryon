<?php

namespace App\FaithFlow\Actions;

use App\Enums\FaithFlowOutputStatus;
use App\Models\FaithFlowOutput;
use InvalidArgumentException;
use LogicException;

/**
 * The deterministic, non-AI human-editing seam — see K-FAITHFLOW-001E §7/
 * §33/§34. Editing changes `content` (the human working copy) only;
 * `generated_content` (the last provider-produced result), `regeneration_
 * count`, and every provenance/usage record are left completely untouched
 * — a human edit is not a generation event and must never be mistaken for
 * one. No AI call, no FaithFlowUsage row (§41).
 *
 * Authorization is deliberately not performed here — the caller's
 * responsibility, mirroring every other FaithFlow Action class's
 * established precedent (see AnalyzeFaithFlowSource).
 */
class EditFaithFlowOutput
{
    public function handle(FaithFlowOutput $output, string $content): FaithFlowOutput
    {
        if ($output->status === FaithFlowOutputStatus::APPROVED) {
            // A distinct message from the generic guard below — approval is
            // a deliberate immutability boundary (§22/§24), not just
            // another invalid state. Further edits belong to Content
            // Studio, on the handed-off ContentItem.
            throw new LogicException('An approved output is immutable — further edits belong to Content Studio.');
        }

        if (! $output->isEditable()) {
            throw new LogicException("An output with status [{$output->status->value}] cannot be edited — only a GENERATED output can.");
        }

        $trimmed = trim($content);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Edited content cannot be empty.');
        }

        $output->forceFill([
            'content' => $trimmed,
            'edited_at' => now(),
        ])->save();

        return $output->fresh();
    }
}
