<?php

namespace App\FaithFlow\Ai;

/**
 * Keryon-owned result of an output-generation provider call — see
 * K-FAITHFLOW-001D. Sibling of AnalysisResult (same shape), kept as its own
 * class so the name stays accurate at every call site rather than reusing an
 * "analysis"-named type for generation.
 */
final readonly class GenerationResult
{
    /**
     * @param  array<string, mixed>|string  $data  the raw payload — a string for text-shaped outputs, an array for structured/list-shaped outputs
     */
    public function __construct(
        public array|string $data,
        public int $promptTokens,
        public int $completionTokens,
        public ?string $provider,
        public ?string $model,
    ) {}
}
