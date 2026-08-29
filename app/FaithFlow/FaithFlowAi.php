<?php

namespace App\FaithFlow;

use App\Enums\AiCapability;
use App\Enums\AiDataOrigin;
use App\Enums\DataClassification;
use App\Enums\FaithFlowOutputType;
use App\FaithFlow\Ai\AnalysisResult;
use App\FaithFlow\Ai\CanonicalAnalysisAgent;
use App\FaithFlow\Ai\GenerationResult;
use App\FaithFlow\Ai\StructuredOutputGenerationAgent;
use App\FaithFlow\Ai\TextOutputGenerationAgent;
use App\Models\FaithFlowRun;
use App\Trust\Ai\AiProcessingPolicy;
use App\Trust\Ai\AiProcessingRequest;
use App\Trust\Ai\KnownCareContentGuard;
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
    public function __construct(
        private readonly AiProcessingPolicy $policy,
        private readonly KnownCareContentGuard $care,
    ) {}

    public function analyze(FaithFlowRun $run): AnalysisResult
    {
        $this->authorize($run, AiCapability::FaithFlowAnalysis, AiDataOrigin::FaithFlowSource);
        $this->care->assertEligible($run->source_text, $run->church_id);

        /** @var StructuredAgentResponse $response */
        $response = (new CanonicalAnalysisAgent)->prompt($run->source_text);

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
    public function generateText(FaithFlowOutputType $type, array $canonicalAnalysis, FaithFlowRun $run): GenerationResult
    {
        $this->authorize($run, AiCapability::FaithFlowGeneration, AiDataOrigin::FaithFlowAnalysis);
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
    public function generateStructured(FaithFlowOutputType $type, array $canonicalAnalysis, FaithFlowRun $run): GenerationResult
    {
        $this->authorize($run, AiCapability::FaithFlowGeneration, AiDataOrigin::FaithFlowAnalysis);
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

    private function authorize(FaithFlowRun $run, AiCapability $capability, AiDataOrigin $origin): void
    {
        $this->policy->authorize(new AiProcessingRequest(
            capability: $capability,
            classification: DataClassification::Sensitive,
            origin: $origin,
            churchId: $run->church_id,
            provider: (string) config('faithflow.provider'),
            model: (string) config('faithflow.model'),
        ));
    }
}
