<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\Enums\FaithFlowRunStatus;
use App\FaithFlow\Actions\GenerateFaithFlowOutput;
use App\FaithFlow\Ai\StructuredOutputGenerationAgent;
use App\FaithFlow\Ai\TextOutputGenerationAgent;
use App\Models\Church;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\FaithFlowUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class GenerateFaithFlowOutputTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Generation Test Church', 'slug' => 'generation-test-church-'.uniqid()]);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
    }

    protected function canonicalAnalysis(array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }

    protected function analyzedRun(array $canonicalAnalysisOverrides = []): FaithFlowRun
    {
        return FaithFlowRun::factory()->forChurch($this->church)->create([
            'status' => FaithFlowRunStatus::ANALYZED,
            'canonical_analysis' => $this->canonicalAnalysis($canonicalAnalysisOverrides),
        ]);
    }

    protected function pendingOutput(FaithFlowRun $run, FaithFlowOutputType $type): FaithFlowOutput
    {
        return FaithFlowOutput::factory()->forRun($run)->create(['output_type' => $type]);
    }

    // --- One deterministic success test per approved output type (§58) ---

    public function test_generates_sermon_summary(): void
    {
        TextOutputGenerationAgent::fake(['A faithful, source-grounded summary of the sermon.']);

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::SERMON_SUMMARY);
        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $result->status);
        $this->assertSame('A faithful, source-grounded summary of the sermon.', $result->generated_content);
        $this->assertSame($result->generated_content, $result->content);
    }

    public function test_generates_devotional(): void
    {
        TextOutputGenerationAgent::fake(['A short devotional drawn from the sermon.']);

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::DEVOTIONAL);
        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $result->status);
        $this->assertSame('A short devotional drawn from the sermon.', $result->generated_content);
    }

    public function test_generates_whatsapp_status_copy(): void
    {
        TextOutputGenerationAgent::fake(['Sunday was a reminder: hope does not disappoint. Join us next week!']);

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::WHATSAPP_STATUS_COPY);
        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $result->status);
        $this->assertNotEmpty($result->generated_content);
    }

    public function test_generates_key_themes(): void
    {
        StructuredOutputGenerationAgent::fake([
            ['themes' => ['Hope', 'Perseverance']],
        ]);

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::KEY_THEMES);
        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $result->status);
        $this->assertSame("- Hope\n- Perseverance", $result->generated_content);
    }

    public function test_generates_prayer_points(): void
    {
        StructuredOutputGenerationAgent::fake([
            ['prayer_points' => ['Pray for renewed hope in hardship.', 'Pray for perseverance in trials.']],
        ]);

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::PRAYER_POINTS);
        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $result->status);
        $this->assertStringContainsString('Pray for renewed hope in hardship.', $result->generated_content);
    }

    public function test_generates_social_captions(): void
    {
        StructuredOutputGenerationAgent::fake([
            ['captions' => ['Hope does not disappoint. 🙏', 'Hard season? Hope holds.']],
        ]);

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::SOCIAL_CAPTIONS);
        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $result->status);
        $this->assertStringContainsString('Hope does not disappoint.', $result->generated_content);
    }

    public function test_generates_discussion_questions(): void
    {
        StructuredOutputGenerationAgent::fake([
            ['questions' => ['Where have you seen hope sustain you through hardship?']],
        ]);

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::DISCUSSION_QUESTIONS);
        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $result->status);
        $this->assertStringContainsString('Where have you seen hope', $result->generated_content);
    }

    public function test_generates_key_quotes_grounded_in_notable_statements(): void
    {
        StructuredOutputGenerationAgent::fake([
            ['quotes' => [
                ['text' => 'Hope does not put us to shame.', 'context' => 'Central exhortation'],
                ['text' => 'This was never in the sermon at all.', 'context' => 'fabricated'],
            ]],
        ]);

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::KEY_QUOTES);
        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $result->status);
        $this->assertStringContainsString('Hope does not put us to shame.', $result->generated_content);
        $this->assertStringNotContainsString('This was never in the sermon at all.', $result->generated_content);
    }

    public function test_key_quotes_with_no_notable_statements_short_circuits_without_a_provider_call(): void
    {
        StructuredOutputGenerationAgent::fake()->preventStrayPrompts();

        $run = $this->analyzedRun(['notable_statements' => []]);
        $output = $this->pendingOutput($run, FaithFlowOutputType::KEY_QUOTES);

        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $result->status);
        $this->assertSame('', $result->generated_content);
        StructuredOutputGenerationAgent::assertNeverPrompted();
        $this->assertSame(0, FaithFlowUsage::query()->where('faithflow_output_id', $output->id)->count());
    }

    // --- Run precondition (§8) ---

    public function test_cannot_generate_against_a_run_that_is_not_analyzed(): void
    {
        $run = FaithFlowRun::factory()->forChurch($this->church)->create(['status' => FaithFlowRunStatus::DRAFT]);
        $output = $this->pendingOutput($run, FaithFlowOutputType::DEVOTIONAL);

        $this->expectException(LogicException::class);

        app(GenerateFaithFlowOutput::class)->handle($output);
    }

    // --- State machine (§27/§60) ---

    public function test_generating_status_denies_a_duplicate_call(): void
    {
        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::DEVOTIONAL);
        $output->forceFill(['status' => FaithFlowOutputStatus::GENERATING])->save();

        $this->expectException(LogicException::class);

        app(GenerateFaithFlowOutput::class)->handle($output->fresh());
    }

    public function test_generated_status_is_idempotent_and_makes_no_new_prompt(): void
    {
        TextOutputGenerationAgent::fake(['A devotional.'])->preventStrayPrompts();

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::DEVOTIONAL);
        $first = app(GenerateFaithFlowOutput::class)->handle($output);
        $second = app(GenerateFaithFlowOutput::class)->handle($first);

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $second->status);
        $this->assertSame($first->generated_content, $second->generated_content);
    }

    public function test_failed_output_cannot_be_approved(): void
    {
        $output = FaithFlowOutput::factory()->failed()->create(['church_id' => $this->church->id]);

        $this->assertFalse($output->canBeApproved());
    }

    public function test_pending_output_cannot_be_approved(): void
    {
        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::DEVOTIONAL);

        $this->assertFalse($output->canBeApproved());
    }

    public function test_generating_output_cannot_be_approved(): void
    {
        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::DEVOTIONAL);
        $output->forceFill(['status' => FaithFlowOutputStatus::GENERATING])->save();

        $this->assertFalse($output->fresh()->canBeApproved());
    }

    public function test_generated_output_can_be_approved(): void
    {
        TextOutputGenerationAgent::fake(['A devotional.']);

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::DEVOTIONAL);
        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertTrue($result->canBeApproved());
    }

    // --- Failure / retry / usage (mirrors AnalyzeFaithFlowSourceTest's proven pattern) ---

    public function test_provider_failure_transitions_a_first_time_generation_to_failed(): void
    {
        TextOutputGenerationAgent::fake(function (): never {
            throw new RuntimeException('Simulated provider outage.');
        });

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::DEVOTIONAL);
        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::FAILED, $result->status);
        $this->assertNull($result->generated_content);
        $this->assertNotNull($result->error_message);
    }

    public function test_failure_message_never_exposes_raw_exception_detail(): void
    {
        TextOutputGenerationAgent::fake(function (): never {
            throw new RuntimeException('raw provider secret payload detail');
        });

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::DEVOTIONAL);
        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertStringNotContainsString('raw provider secret payload detail', (string) $result->error_message);
    }

    public function test_retry_recovers_from_a_single_transient_failure(): void
    {
        $attempts = 0;

        TextOutputGenerationAgent::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new RuntimeException('Transient failure.');
            }

            return 'A devotional, on the second attempt.';
        });

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::DEVOTIONAL);
        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $result->status);
        $this->assertSame(2, FaithFlowUsage::query()->where('faithflow_output_id', $output->id)->count());
    }

    public function test_retry_is_bounded_to_two_attempts(): void
    {
        TextOutputGenerationAgent::fake(function (): never {
            throw new RuntimeException('Always fails.');
        });

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::DEVOTIONAL);
        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::FAILED, $result->status);
        $this->assertSame(2, FaithFlowUsage::query()->where('faithflow_output_id', $output->id)->count());
    }

    public function test_malformed_response_is_rejected(): void
    {
        // Missing the required 'themes' key entirely.
        StructuredOutputGenerationAgent::fake([[], []]);

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::KEY_THEMES);
        $result = app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::FAILED, $result->status);
    }

    public function test_successful_generation_records_usage_with_provenance(): void
    {
        TextOutputGenerationAgent::fake(['A devotional.']);

        $output = $this->pendingOutput($this->analyzedRun(), FaithFlowOutputType::DEVOTIONAL);
        app(GenerateFaithFlowOutput::class)->handle($output);

        $this->assertDatabaseHas('faithflow_usage', [
            'faithflow_output_id' => $output->id,
            'faithflow_run_id' => $output->faithflow_run_id,
            'church_id' => $this->church->id,
            'operation' => 'generate',
            'status' => 'success',
        ]);
    }
}
