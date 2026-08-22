<?php

namespace App\FaithFlow\Actions;

use App\Enums\FaithFlowOutputType;
use App\Enums\FaithFlowRunStatus;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use Illuminate\Database\QueryException;
use LogicException;
use Throwable;

/**
 * Multi-output orchestration — see K-FAITHFLOW-001D §9/§12. Creates
 * FaithFlowOutput rows for any selected types that don't already exist
 * (§9 — one run may have any subset of the 8 approved types), then generates
 * each independently. One type's failure never stops the others (§10/§36) —
 * this method never throws for a per-output problem; it only throws once,
 * up front, for the run-level precondition (§8).
 */
class GenerateSelectedFaithFlowOutputs
{
    public function __construct(private readonly GenerateFaithFlowOutput $generate) {}

    /**
     * @param  array<int, FaithFlowOutputType>  $outputTypes
     * @return array<int, FaithFlowOutput>
     */
    public function handle(FaithFlowRun $run, array $outputTypes): array
    {
        if ($run->status !== FaithFlowRunStatus::ANALYZED || $run->canonical_analysis === null) {
            throw new LogicException('This run has no valid canonical analysis to generate from.');
        }

        $results = [];

        foreach ($outputTypes as $type) {
            $output = $this->findOrCreateOutput($run, $type);

            try {
                $results[] = $this->generate->handle($output);
            } catch (Throwable) {
                // GenerateFaithFlowOutput already absorbs ordinary provider/
                // validation failures internally (it never throws for
                // those — see its own docblock). This only guards against
                // something unexpected so the batch still completes for the
                // remaining selected outputs, per §10/§36.
                $results[] = $output->fresh();
            }
        }

        return $results;
    }

    private function findOrCreateOutput(FaithFlowRun $run, FaithFlowOutputType $type): FaithFlowOutput
    {
        $existing = FaithFlowOutput::query()
            ->where('faithflow_run_id', $run->id)
            ->where('output_type', $type->value)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // church_id/faithflow_run_id are deliberately not mass-assignable
        // (mirrors FaithFlowRun/FaithFlowOutput's own posture) — set
        // directly, mass-assign only output_type.
        $output = new FaithFlowOutput(['output_type' => $type]);
        $output->church_id = $run->church_id;
        $output->faithflow_run_id = $run->id;

        try {
            $output->save();
        } catch (QueryException) {
            // Lost a race to create the same (run, type) row — the unique
            // constraint from K-FAITHFLOW-001B already protects this; fetch
            // whichever row the other request created instead.
            return FaithFlowOutput::query()
                ->where('faithflow_run_id', $run->id)
                ->where('output_type', $type->value)
                ->firstOrFail();
        }

        return $output;
    }
}
