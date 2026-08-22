<?php

namespace App\FaithFlow\Analysis;

use App\FaithFlow\Analysis\Exceptions\MalformedCanonicalAnalysisException;

/**
 * The approved canonical-analysis contract — see K-FAITHFLOW-001A §11 and
 * K-FAITHFLOW-001C §7/§8/§9. Exactly the 8 fields 001A approved; no more, no
 * fewer (see K-FAITHFLOW-001C's own §8 instruction not to invent unsupported
 * dimensions, and the schema-reconciliation note in this milestone's report).
 *
 * fromProviderResponse() is the only construction path and performs two
 * distinct things:
 *   1. Structural validation — every required key must be present and of
 *      the correct shape, or MalformedCanonicalAnalysisException is thrown.
 *      This is deliberately independent of whatever the AI provider's own
 *      structured-output guarantee is — malformed output must be
 *      detectable by Keryon's own code, not merely trusted.
 *   2. Grounding — notable_statements and scripture_references are only
 *      kept when they appear, verbatim (whitespace/case-normalized), as a
 *      substring of the supplied source text. Anything not literally
 *      present is dropped, never persisted as if it were a real source
 *      quote or reference — see K-FAITHFLOW-001C §12/§13 (quote integrity,
 *      scripture handling) and K-FAITHFLOW-001A §11 (no fabricated
 *      scripture/quotations). This is a deterministic, provider-independent
 *      guardrail, not just a prompt instruction.
 */
final readonly class CanonicalAnalysis
{
    /**
     * @param  array<int, string>  $keyThemes
     * @param  array<int, array{text: string, context: string}>  $notableStatements
     * @param  array<int, array{reference: string, context: string}>  $scriptureReferences
     */
    public function __construct(
        public string $sourceSummary,
        public string $principalMessage,
        public array $keyThemes,
        public array $notableStatements,
        public array $scriptureReferences,
        public ?string $ministryContext,
        public ?string $audienceClues,
        public ?string $tone,
    ) {}

    public static function fromProviderResponse(array $data, string $sourceText): self
    {
        self::assertString($data, 'source_summary');
        self::assertString($data, 'principal_message');
        self::assertStringArray($data, 'key_themes');
        self::assertObjectArray($data, 'notable_statements', ['text', 'context']);
        self::assertObjectArray($data, 'scripture_references', ['reference', 'context']);
        self::assertNullableString($data, 'ministry_context');
        self::assertNullableString($data, 'audience_clues');
        self::assertNullableString($data, 'tone');

        return new self(
            sourceSummary: $data['source_summary'],
            principalMessage: $data['principal_message'],
            keyThemes: array_values($data['key_themes']),
            notableStatements: self::grounded($data['notable_statements'], $sourceText, 'text'),
            scriptureReferences: self::grounded($data['scripture_references'], $sourceText, 'reference'),
            ministryContext: $data['ministry_context'],
            audienceClues: $data['audience_clues'],
            tone: $data['tone'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_summary' => $this->sourceSummary,
            'principal_message' => $this->principalMessage,
            'key_themes' => $this->keyThemes,
            'notable_statements' => $this->notableStatements,
            'scripture_references' => $this->scriptureReferences,
            'ministry_context' => $this->ministryContext,
            'audience_clues' => $this->audienceClues,
            'tone' => $this->tone,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function grounded(array $items, string $sourceText, string $key): array
    {
        $normalizedSource = self::normalize($sourceText);

        return array_values(array_filter(
            $items,
            fn (array $item): bool => str_contains($normalizedSource, self::normalize($item[$key]))
        ));
    }

    private static function normalize(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $text)));
    }

    private static function assertString(array $data, string $key): void
    {
        if (! array_key_exists($key, $data) || ! is_string($data[$key]) || $data[$key] === '') {
            throw new MalformedCanonicalAnalysisException("Canonical analysis is missing a required text field: {$key}.");
        }
    }

    private static function assertNullableString(array $data, string $key): void
    {
        if (! array_key_exists($key, $data)) {
            throw new MalformedCanonicalAnalysisException("Canonical analysis is missing field: {$key}.");
        }

        if ($data[$key] !== null && ! is_string($data[$key])) {
            throw new MalformedCanonicalAnalysisException("Canonical analysis field {$key} must be a string or null.");
        }
    }

    private static function assertStringArray(array $data, string $key): void
    {
        if (! array_key_exists($key, $data) || ! is_array($data[$key])) {
            throw new MalformedCanonicalAnalysisException("Canonical analysis is missing a required list field: {$key}.");
        }

        foreach ($data[$key] as $item) {
            if (! is_string($item)) {
                throw new MalformedCanonicalAnalysisException("Canonical analysis field {$key} must contain only strings.");
            }
        }
    }

    /**
     * @param  array<int, string>  $requiredItemKeys
     */
    private static function assertObjectArray(array $data, string $key, array $requiredItemKeys): void
    {
        if (! array_key_exists($key, $data) || ! is_array($data[$key])) {
            throw new MalformedCanonicalAnalysisException("Canonical analysis is missing a required list field: {$key}.");
        }

        foreach ($data[$key] as $item) {
            if (! is_array($item)) {
                throw new MalformedCanonicalAnalysisException("Canonical analysis field {$key} must contain objects.");
            }

            foreach ($requiredItemKeys as $itemKey) {
                if (! array_key_exists($itemKey, $item) || ! is_string($item[$itemKey])) {
                    throw new MalformedCanonicalAnalysisException("Canonical analysis field {$key} entries require a {$itemKey} string.");
                }
            }
        }
    }
}
