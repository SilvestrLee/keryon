<?php

namespace Tests\Unit\FaithFlow;

use App\FaithFlow\Analysis\CanonicalAnalysis;
use App\FaithFlow\Analysis\Exceptions\MalformedCanonicalAnalysisException;
use PHPUnit\Framework\TestCase;

class CanonicalAnalysisTest extends TestCase
{
    protected function validPayload(): array
    {
        return [
            'source_summary' => 'A sermon about hope in difficult seasons.',
            'principal_message' => 'God remains faithful even in hardship.',
            'key_themes' => ['hope', 'perseverance'],
            'notable_statements' => [
                ['text' => 'Hope does not put us to shame.', 'context' => 'Central exhortation'],
            ],
            'scripture_references' => [
                ['reference' => 'Romans 5:5', 'context' => 'Quoted directly'],
            ],
            'ministry_context' => 'Sunday sermon',
            'audience_clues' => 'General congregation',
            'tone' => 'Encouraging',
        ];
    }

    public function test_valid_payload_produces_a_canonical_analysis(): void
    {
        $sourceText = 'Today we talk about hope. Hope does not put us to shame, as Romans 5:5 tells us.';

        $analysis = CanonicalAnalysis::fromProviderResponse($this->validPayload(), $sourceText);

        $this->assertSame('A sermon about hope in difficult seasons.', $analysis->sourceSummary);
        $this->assertSame(['hope', 'perseverance'], $analysis->keyThemes);
        $this->assertSame('Sunday sermon', $analysis->ministryContext);
    }

    public function test_to_array_round_trips_to_the_persisted_shape(): void
    {
        $sourceText = 'Hope does not put us to shame. Romans 5:5.';
        $analysis = CanonicalAnalysis::fromProviderResponse($this->validPayload(), $sourceText);

        $array = $analysis->toArray();

        $this->assertSame('A sermon about hope in difficult seasons.', $array['source_summary']);
        $this->assertArrayHasKey('notable_statements', $array);
        $this->assertArrayHasKey('scripture_references', $array);
    }

    public function test_nullable_fields_may_be_null(): void
    {
        $payload = $this->validPayload();
        $payload['ministry_context'] = null;
        $payload['audience_clues'] = null;
        $payload['tone'] = null;

        $analysis = CanonicalAnalysis::fromProviderResponse($payload, 'Hope does not put us to shame.');

        $this->assertNull($analysis->ministryContext);
        $this->assertNull($analysis->audienceClues);
        $this->assertNull($analysis->tone);
    }

    public function test_empty_notable_statements_and_scripture_references_are_valid(): void
    {
        $payload = $this->validPayload();
        $payload['notable_statements'] = [];
        $payload['scripture_references'] = [];

        $analysis = CanonicalAnalysis::fromProviderResponse($payload, 'Some source text.');

        $this->assertSame([], $analysis->notableStatements);
        $this->assertSame([], $analysis->scriptureReferences);
    }

    public function test_missing_required_field_throws(): void
    {
        $payload = $this->validPayload();
        unset($payload['source_summary']);

        $this->expectException(MalformedCanonicalAnalysisException::class);

        CanonicalAnalysis::fromProviderResponse($payload, 'Source text.');
    }

    public function test_wrong_type_for_key_themes_throws(): void
    {
        $payload = $this->validPayload();
        $payload['key_themes'] = 'hope, perseverance';

        $this->expectException(MalformedCanonicalAnalysisException::class);

        CanonicalAnalysis::fromProviderResponse($payload, 'Source text.');
    }

    public function test_wrong_type_inside_notable_statements_throws(): void
    {
        $payload = $this->validPayload();
        $payload['notable_statements'] = [['text' => 'Hope does not put us to shame.']]; // missing 'context'

        $this->expectException(MalformedCanonicalAnalysisException::class);

        CanonicalAnalysis::fromProviderResponse($payload, 'Source text.');
    }

    public function test_missing_nullable_field_key_still_throws(): void
    {
        // The key itself must be present (even if its value is null) — a
        // provider silently omitting the field entirely is malformed.
        $payload = $this->validPayload();
        unset($payload['tone']);

        $this->expectException(MalformedCanonicalAnalysisException::class);

        CanonicalAnalysis::fromProviderResponse($payload, 'Source text.');
    }

    public function test_ungrounded_notable_statement_is_dropped_not_persisted(): void
    {
        $sourceText = 'Hope does not put us to shame, Paul writes.';

        $payload = $this->validPayload();
        $payload['notable_statements'] = [
            ['text' => 'Hope does not put us to shame,', 'context' => 'grounded'],
            ['text' => 'This quote was never in the source at all.', 'context' => 'fabricated'],
        ];

        $analysis = CanonicalAnalysis::fromProviderResponse($payload, $sourceText);

        $this->assertCount(1, $analysis->notableStatements);
        $this->assertSame('Hope does not put us to shame,', $analysis->notableStatements[0]['text']);
    }

    public function test_ungrounded_scripture_reference_is_dropped_not_persisted(): void
    {
        $sourceText = 'As Romans 5:5 tells us, hope does not put us to shame.';

        $payload = $this->validPayload();
        $payload['scripture_references'] = [
            ['reference' => 'Romans 5:5', 'context' => 'grounded'],
            ['reference' => 'John 3:16', 'context' => 'never mentioned in source'],
        ];

        $analysis = CanonicalAnalysis::fromProviderResponse($payload, $sourceText);

        $this->assertCount(1, $analysis->scriptureReferences);
        $this->assertSame('Romans 5:5', $analysis->scriptureReferences[0]['reference']);
    }

    public function test_grounding_check_is_whitespace_and_case_insensitive(): void
    {
        $sourceText = "Hope   does NOT put\nus to shame.";

        $payload = $this->validPayload();
        $payload['notable_statements'] = [
            ['text' => 'hope does not put us to shame.', 'context' => 'normalized match'],
        ];

        $analysis = CanonicalAnalysis::fromProviderResponse($payload, $sourceText);

        $this->assertCount(1, $analysis->notableStatements);
    }

    public function test_all_notable_statements_fabricated_yields_empty_list_not_an_error(): void
    {
        $payload = $this->validPayload();
        $payload['notable_statements'] = [
            ['text' => 'Completely invented statement.', 'context' => 'fabricated'],
        ];

        $analysis = CanonicalAnalysis::fromProviderResponse($payload, 'Nothing matching this at all.');

        $this->assertSame([], $analysis->notableStatements);
    }
}
