<?php

namespace App\FaithFlow\Actions;

use App\Enums\FaithFlowRunStatus;
use App\FaithFlow\Analysis\CanonicalAnalysis;
use App\FaithFlow\Analysis\Exceptions\MalformedCanonicalAnalysisException;
use App\FaithFlow\Ai\CanonicalAnalysisAgent;
use App\FaithFlow\FaithFlowAi;
use App\Models\FaithFlowRun;
use App\Models\FaithFlowUsage;
use LogicException;
use Throwable;

/**
 * The canonical-analysis orchestration seam — see K-FAITHFLOW-001C §16.
 *
 * SOURCE -> AI boundary -> validated CanonicalAnalysis -> persist -> run
 * state updated -> usage recorded. This class is the only place that
 * sequence happens; Filament pages, controllers, and models never
 * orchestrate this directly (K-FAITHFLOW-001C §16).
 *
 * Authorization is deliberately NOT performed here — it is the caller's
 * responsibility (e.g. a future Filament action calling
 * Gate::authorize('analyze', $run) immediately before invoking handle()),
 * mirroring exactly how ContentItem's own workflow actions are authorized
 * one layer up from the domain method, never inside it.
 */
class AnalyzeFaithFlowSource
{
    /** Bounded per K-FAITHFLOW-001C §19 — one automatic retry, no more. */
    private const MAX_ATTEMPTS = 2;

    public function __construct(private readonly FaithFlowAi $ai) {}

    public function handle(FaithFlowRun $run): FaithFlowRun
    {
        if ($run->status === FaithFlowRunStatus::ANALYZED) {
            // Idempotent — return the existing analysis rather than
            // silently re-spending on an already-successful run. See
            // K-FAITHFLOW-001C §20 / K-FAITHFLOW-001A §18.
            return $run;
        }

        if ($run->status === FaithFlowRunStatus::ANALYZING) {
            throw new LogicException('This run is already being analyzed.');
        }

        if (! in_array($run->status, [FaithFlowRunStatus::DRAFT, FaithFlowRunStatus::ANALYSIS_FAILED], true)) {
            throw new LogicException("A run with status [{$run->status->value}] cannot be analyzed.");
        }

        $run->forceFill(['status' => FaithFlowRunStatus::ANALYZING])->save();

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $startedAt = microtime(true);
            $result = null;

            try {
                $result = $this->ai->analyze($run->source_text);
                $analysis = CanonicalAnalysis::fromProviderResponse($result->data, $run->source_text);
            } catch (Throwable $e) {
                $this->recordUsage($run, $startedAt, 'failed', $this->errorCategoryFor($e), $result);

                $run->forceFill([
                    'analysis_attempts' => $run->analysis_attempts + 1,
                ])->save();

                if ($attempt < self::MAX_ATTEMPTS) {
                    continue;
                }

                $run->forceFill([
                    'status' => FaithFlowRunStatus::ANALYSIS_FAILED,
                    'analysis_error' => $this->safeMessage($e),
                ])->save();

                return $run->fresh();
            }

            $this->recordUsage($run, $startedAt, 'success', null, $result);

            $run->forceFill([
                'status' => FaithFlowRunStatus::ANALYZED,
                'canonical_analysis' => $analysis->toArray(),
                'analysis_error' => null,
                'analysis_attempts' => $run->analysis_attempts + 1,
                'prompt_version' => CanonicalAnalysisAgent::PROMPT_VERSION,
            ])->save();

            return $run->fresh();
        }

        // Unreachable — the loop above always returns — but keeps static
        // analysis and callers honest about the return type.
        return $run->fresh();
    }

    private function errorCategoryFor(Throwable $e): string
    {
        return $e instanceof MalformedCanonicalAnalysisException
            ? 'malformed_response'
            : 'provider_failure';
    }

    /**
     * Never persists prompt/response text or provider payloads — see
     * K-FAITHFLOW-001C §18/§23/§25. Metadata only.
     */
    private function recordUsage(
        FaithFlowRun $run,
        float $startedAt,
        string $status,
        ?string $errorCategory,
        mixed $result,
    ): void {
        // church_id/faithflow_run_id are deliberately not mass-assignable
        // (mirrors FaithFlowRun/FaithFlowOutput's own posture) — set
        // directly, mass-assign only the safe metadata fields.
        $usage = new FaithFlowUsage([
            'operation' => 'analysis',
            'provider' => $result?->provider ?? config('faithflow.provider'),
            'model' => $result?->model ?? config('faithflow.model'),
            // Column added in K-FAITHFLOW-001D §38 — recording it here too
            // now that it exists; not a behavior change to 001C.
            'prompt_version' => CanonicalAnalysisAgent::PROMPT_VERSION,
            'input_tokens' => $result?->promptTokens,
            'output_tokens' => $result?->completionTokens,
            'latency_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            'status' => $status,
            'error_category' => $errorCategory,
        ]);
        $usage->church_id = $run->church_id;
        $usage->faithflow_run_id = $run->id;
        $usage->save();
    }

    private function safeMessage(Throwable $e): string
    {
        return $e instanceof MalformedCanonicalAnalysisException
            ? $e->getMessage()
            : 'The AI provider could not complete this analysis.';
    }
}
