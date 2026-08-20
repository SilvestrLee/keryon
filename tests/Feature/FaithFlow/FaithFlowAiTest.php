<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\FaithFlowOutputType;
use App\FaithFlow\Ai\AnalysisResult;
use App\FaithFlow\Ai\CanonicalAnalysisAgent;
use App\FaithFlow\Ai\GenerationResult;
use App\FaithFlow\Ai\StructuredOutputGenerationAgent;
use App\FaithFlow\Ai\TextOutputGenerationAgent;
use App\FaithFlow\FaithFlowAi;
use RuntimeException;
use Tests\TestCase;

/**
 * Proves the provider boundary itself (K-FAITHFLOW-001B §17/§53) — install,
 * container binding, structured output, deterministic fake testing.
 * CanonicalAnalysisAgent's schema is the real K-FAITHFLOW-001C contract;
 * TextOutputGenerationAgent/StructuredOutputGenerationAgent are the real
 * K-FAITHFLOW-001D contracts (see GenerateFaithFlowOutputTest for the
 * orchestration behavior built on top of them).
 */
class FaithFlowAiTest extends TestCase
{
    public function test_faithflow_ai_resolves_from_the_container(): void
    {
        $this->assertInstanceOf(FaithFlowAi::class, app(FaithFlowAi::class));
    }

    public function test_faithflow_ai_is_a_singleton(): void
    {
        $this->assertSame(app(FaithFlowAi::class), app(FaithFlowAi::class));
    }

    public function test_analyze_returns_a_deterministic_typed_result_from_the_fake_provider(): void
    {
        CanonicalAnalysisAgent::fake([
            ['summary' => 'A faithful, source-grounded summary.'],
        ]);

        $result = app(FaithFlowAi::class)->analyze('Sunday sermon notes about hope.');

        $this->assertInstanceOf(AnalysisResult::class, $result);
        $this->assertSame(['summary' => 'A faithful, source-grounded summary.'], $result->data);
        $this->assertSame('anthropic', $result->provider);
        $this->assertSame('claude-sonnet-5', $result->model);
        CanonicalAnalysisAgent::assertPrompted('Sunday sermon notes about hope.');
    }

    public function test_generate_text_returns_a_deterministic_typed_result_from_the_fake_provider(): void
    {
        TextOutputGenerationAgent::fake([
            'A generated devotional.',
        ]);

        $result = app(FaithFlowAi::class)->generateText(FaithFlowOutputType::DEVOTIONAL, ['principal_message' => 'Placeholder analysis.']);

        $this->assertInstanceOf(GenerationResult::class, $result);
        $this->assertSame('A generated devotional.', $result->data);
    }

    public function test_generate_structured_returns_a_deterministic_typed_result_from_the_fake_provider(): void
    {
        StructuredOutputGenerationAgent::fake([
            ['prayer_points' => ['Pray for peace.']],
        ]);

        $result = app(FaithFlowAi::class)->generateStructured(FaithFlowOutputType::PRAYER_POINTS, ['principal_message' => 'Placeholder analysis.']);

        $this->assertInstanceOf(GenerationResult::class, $result);
        $this->assertSame(['prayer_points' => ['Pray for peace.']], $result->data);
    }

    public function test_fake_provider_can_simulate_failure_without_a_network_call(): void
    {
        CanonicalAnalysisAgent::fake(function (): never {
            throw new RuntimeException('Simulated provider failure.');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Simulated provider failure.');

        app(FaithFlowAi::class)->analyze('Sunday sermon notes.');
    }

    public function test_no_prompt_is_sent_when_the_agent_is_never_invoked(): void
    {
        CanonicalAnalysisAgent::fake();

        CanonicalAnalysisAgent::assertNeverPrompted();
    }

    public function test_fake_prevents_stray_prompts_without_a_defined_response(): void
    {
        StructuredOutputGenerationAgent::fake()->preventStrayPrompts();

        $this->expectException(RuntimeException::class);

        app(FaithFlowAi::class)->generateStructured(FaithFlowOutputType::PRAYER_POINTS, []);
    }
}
