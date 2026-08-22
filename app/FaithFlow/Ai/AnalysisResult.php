<?php

namespace App\FaithFlow\Ai;

/**
 * Keryon-owned result of a canonical-analysis provider call — see
 * K-FAITHFLOW-001C. Exists so callers of FaithFlowAi::analyze() (the
 * orchestration Action, in app/FaithFlow/Actions/) never need to reference
 * Laravel\Ai\* directly to get at usage/provenance data. Every reference to
 * the vendor's response types stays confined to FaithFlowAi and the Agent
 * classes in this directory.
 */
final readonly class AnalysisResult
{
    /**
     * @param  array<string, mixed>  $data  the raw structured-output payload, unvalidated
     */
    public function __construct(
        public array $data,
        public int $promptTokens,
        public int $completionTokens,
        public ?string $provider,
        public ?string $model,
    ) {}
}
