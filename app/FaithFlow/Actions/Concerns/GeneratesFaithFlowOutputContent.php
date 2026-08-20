<?php

namespace App\FaithFlow\Actions\Concerns;

use App\Enums\FaithFlowOutputResultShape;
use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\FaithFlow\Actions\Exceptions\MalformedGeneratedOutputException;
use App\FaithFlow\Ai\GenerationResult;
use App\FaithFlow\Ai\TextOutputGenerationAgent;
use App\FaithFlow\FaithFlowAi;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowUsage;
use Throwable;

/**
 * The shared core behind GenerateFaithFlowOutput and RegenerateFaithFlowOutput
 * — see K-FAITHFLOW-001D §11/§29. One retry-loop/provider-call/validate/
 * render/persist/usage-record implementation, parametrized by whether this
 * is a first-time generation or a regeneration, so the two Actions stay
 * genuinely distinct, individually-named domain intentions (§31) without
 * duplicating this logic twice.
 */
trait GeneratesFaithFlowOutputContent
{
    private const MAX_ATTEMPTS = 2;

    protected function performGeneration(FaithFlowOutput $output, FaithFlowAi $ai, bool $isRegeneration): FaithFlowOutput
    {
        $canonicalAnalysis = $output->run->canonical_analysis ?? [];
        $operation = $isRegeneration ? 'regenerate' : 'generate';

        // Key Quotes must never manufacture a quote — see K-FAITHFLOW-001D
        // §19. With nothing groundable to select from, skip the provider
        // call entirely (nothing to observe, so no usage row either) and
        // persist an empty, successful result.
        if (
            $output->output_type === FaithFlowOutputType::KEY_QUOTES
            && empty($canonicalAnalysis['notable_statements'] ?? [])
        ) {
            return $this->persistSuccess($output, '', $isRegeneration);
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $startedAt = microtime(true);
            $result = null;

            try {
                $result = $output->output_type->resultShape() === FaithFlowOutputResultShape::TEXT
                    ? $ai->generateText($output->output_type, $canonicalAnalysis)
                    : $ai->generateStructured($output->output_type, $canonicalAnalysis);

                $rendered = $this->validateAndRender($output->output_type, $result->data, $canonicalAnalysis);
            } catch (Throwable $e) {
                $this->recordUsage($output, $startedAt, 'failed', $this->errorCategoryFor($e), $result, $operation);

                if ($attempt < self::MAX_ATTEMPTS) {
                    continue;
                }

                return $this->persistFailure($output, $e, $isRegeneration);
            }

            $this->recordUsage($output, $startedAt, 'success', null, $result, $operation);

            return $this->persistSuccess($output, $rendered, $isRegeneration);
        }

        // Unreachable — the loop above always returns.
        return $output->fresh();
    }

    /**
     * See K-FAITHFLOW-001D §30/§61 — the core human-control invariant.
     * First-time generation always sets both fields (edited_at can't be set
     * yet). A regeneration only overwrites the working content if the human
     * hasn't diverged from it; otherwise the human's edit is preserved and
     * only generated_content (the reference copy) moves.
     */
    private function persistSuccess(FaithFlowOutput $output, string $rendered, bool $isRegeneration): FaithFlowOutput
    {
        $attributes = [
            'status' => FaithFlowOutputStatus::GENERATED,
            'generated_content' => $rendered,
            'error_message' => null,
        ];

        if (! $isRegeneration || ! $output->isEdited()) {
            $attributes['content'] = $rendered;
        }

        $output->forceFill($attributes)->save();

        return $output->fresh();
    }

    /**
     * See K-FAITHFLOW-001D §28 — a failed regeneration must not destroy
     * previously usable content. A first-time failure has no content to
     * protect, so it becomes FAILED; a regeneration failure reverts to
     * GENERATED with the prior content completely untouched, preserving the
     * invariant that FAILED always means "no usable content ever existed"
     * (see FaithFlowOutput::canBeApproved()).
     */
    private function persistFailure(FaithFlowOutput $output, Throwable $e, bool $isRegeneration): FaithFlowOutput
    {
        $output->forceFill([
            'status' => $isRegeneration ? FaithFlowOutputStatus::GENERATED : FaithFlowOutputStatus::FAILED,
            'error_message' => $this->safeMessage($e),
        ])->save();

        return $output->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAndRender(FaithFlowOutputType $type, array|string $data, array $canonicalAnalysis): string
    {
        return match ($type->resultShape()) {
            FaithFlowOutputResultShape::TEXT => $this->validateAndRenderText($data),
            FaithFlowOutputResultShape::LIST => $this->validateAndRenderList($type, $data, $canonicalAnalysis),
        };
    }

    private function validateAndRenderText(array|string $data): string
    {
        if (! is_string($data) || trim($data) === '') {
            throw new MalformedGeneratedOutputException('Generated output was empty or not text.');
        }

        return trim($data);
    }

    private function validateAndRenderList(FaithFlowOutputType $type, array|string $data, array $canonicalAnalysis): string
    {
        if (! is_array($data)) {
            throw new MalformedGeneratedOutputException('Generated output was not structured as expected.');
        }

        return match ($type) {
            FaithFlowOutputType::KEY_THEMES => $this->renderStringList($data, 'themes'),
            FaithFlowOutputType::PRAYER_POINTS => $this->renderStringList($data, 'prayer_points'),
            FaithFlowOutputType::SOCIAL_CAPTIONS => $this->renderStringList($data, 'captions'),
            FaithFlowOutputType::DISCUSSION_QUESTIONS => $this->renderStringList($data, 'questions'),
            FaithFlowOutputType::KEY_QUOTES => $this->renderGroundedQuotes($data, $canonicalAnalysis),
            default => throw new MalformedGeneratedOutputException("Unexpected list-shaped output type: {$type->value}."),
        };
    }

    private function renderStringList(array $data, string $key): string
    {
        if (! array_key_exists($key, $data) || ! is_array($data[$key])) {
            throw new MalformedGeneratedOutputException("Generated output is missing the expected '{$key}' list.");
        }

        foreach ($data[$key] as $item) {
            if (! is_string($item)) {
                throw new MalformedGeneratedOutputException("Generated output's '{$key}' list must contain only strings.");
            }
        }

        return collect($data[$key])->map(fn (string $item): string => '- '.trim($item))->implode("\n");
    }

    /**
     * The deterministic Key Quotes grounding guard — see K-FAITHFLOW-001D
     * §19. Keeps only quotes whose text matches (normalized, verbatim) one
     * of the input canonical analysis's own notable_statements — never
     * trusts the model's stated compliance alone, exactly mirroring
     * CanonicalAnalysis::fromProviderResponse()'s own grounding filter from
     * K-FAITHFLOW-001C.
     */
    private function renderGroundedQuotes(array $data, array $canonicalAnalysis): string
    {
        if (! array_key_exists('quotes', $data) || ! is_array($data['quotes'])) {
            throw new MalformedGeneratedOutputException("Generated output is missing the expected 'quotes' list.");
        }

        $sourceTexts = collect($canonicalAnalysis['notable_statements'] ?? [])
            ->pluck('text')
            ->map(fn ($text): string => $this->normalize((string) $text));

        $grounded = [];

        foreach ($data['quotes'] as $quote) {
            if (! is_array($quote) || ! isset($quote['text']) || ! is_string($quote['text'])) {
                throw new MalformedGeneratedOutputException("Generated output's 'quotes' entries must include text.");
            }

            if ($sourceTexts->contains($this->normalize($quote['text']))) {
                $grounded[] = $quote;
            }
        }

        return collect($grounded)
            ->map(function (array $quote): string {
                $context = trim((string) ($quote['context'] ?? ''));

                return '"'.trim($quote['text']).'"'.($context !== '' ? ' - '.$context : '');
            })
            ->implode("\n\n");
    }

    private function normalize(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $text)));
    }

    private function errorCategoryFor(Throwable $e): string
    {
        return $e instanceof MalformedGeneratedOutputException
            ? 'malformed_response'
            : 'provider_failure';
    }

    private function safeMessage(Throwable $e): string
    {
        return $e instanceof MalformedGeneratedOutputException
            ? $e->getMessage()
            : 'The AI provider could not complete this generation.';
    }

    /**
     * Never persists prompt/response text or provider payloads — see
     * K-FAITHFLOW-001D §37/§57.
     */
    private function recordUsage(
        FaithFlowOutput $output,
        float $startedAt,
        string $status,
        ?string $errorCategory,
        ?GenerationResult $result,
        string $operation,
    ): void {
        $usage = new FaithFlowUsage([
            'operation' => $operation,
            'provider' => $result?->provider ?? config('faithflow.provider'),
            'model' => $result?->model ?? config('faithflow.model'),
            'prompt_version' => TextOutputGenerationAgent::PROMPT_VERSION,
            'input_tokens' => $result?->promptTokens,
            'output_tokens' => $result?->completionTokens,
            'latency_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            'status' => $status,
            'error_category' => $errorCategory,
        ]);
        $usage->church_id = $output->church_id;
        $usage->faithflow_run_id = $output->faithflow_run_id;
        $usage->faithflow_output_id = $output->id;
        $usage->save();
    }
}
