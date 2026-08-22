<?php

namespace App\FaithFlow;

use App\Enums\FaithFlowOutputType;
use App\FaithFlow\Ai\AnalysisResult;
use App\FaithFlow\Ai\CanonicalAnalysisAgent;
use App\FaithFlow\Ai\GenerationResult;
use App\FaithFlow\Ai\StructuredOutputGenerationAgent;
use App\FaithFlow\Ai\TextOutputGenerationAgent;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Keryon's own FaithFlow provider boundary — see K-FAITHFLOW-001B §17.
 *
 * Nothing outside app/FaithFlow/Ai/ (and this class itself) should ever
 * reference Laravel\Ai\* directly. All FaithFlow Actions depend on this
 * class only (and on the Keryon-owned AnalysisResult/GenerationResult it
 * returns), which in turn depends on the Agent classes (the adapter layer),
 * which in turn depend on the laravel/ai package and its provider drivers.
 *
 * analyze() is real as of K-FAITHFLOW-001C. generateText()/generateStructured()
 * are real as of K-FAITHFLOW-001D — both are thin pass-throughs (no grounding
 * validation, no retry policy, no rendering) by design: that orchestration
 * lives in the Action classes under app/FaithFlow/Actions/, not here.
 */
class FaithFlowAi
{
    public function analyze(string $sourceText): AnalysisResult
    {
        /** @var StructuredAgentResponse $response */
        $response = (new CanonicalAnalysisAgent)->prompt($sourceText);

        return new AnalysisResult(
            data: $response->toArray(),
            promptTokens: $response->usage->promptTokens,
            completionTokens: $response->usage->completionTokens,
            provider: $response->meta->provider,
            model: $response->meta->model,
        );
    }

    /**
     * @param  array<string, mixed>  $canonicalAnalysis
     */
    public function generateText(FaithFlowOutputType $type, array $canonicalAnalysis): GenerationResult
    {
        /** @var AgentResponse $response */
        $response = (new TextOutputGenerationAgent($type))->prompt(json_encode($canonicalAnalysis));

        return new GenerationResult(
            data: (string) $response,
            promptTokens: $response->usage->promptTokens,
            completionTokens: $response->usage->completionTokens,
            provider: $response->meta->provider,
            model: $response->meta->model,
        );
    }

    /**
     * @param  array<string, mixed>  $canonicalAnalysis
     */
    public function generateStructured(FaithFlowOutputType $type, array $canonicalAnalysis): GenerationResult
    {
        /** @var StructuredAgentResponse $response */
        $response = (new StructuredOutputGenerationAgent($type))->prompt(json_encode($canonicalAnalysis));

        return new GenerationResult(
            data: $response->toArray(),
            promptTokens: $response->usage->promptTokens,
            completionTokens: $response->usage->completionTokens,
            provider: $response->meta->provider,
            model: $response->meta->model,
        );
    }
}
