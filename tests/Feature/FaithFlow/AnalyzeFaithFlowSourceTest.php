<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowRunStatus;
use App\FaithFlow\Actions\AnalyzeFaithFlowSource;
use App\FaithFlow\Ai\CanonicalAnalysisAgent;
use App\Models\Church;
use App\Models\FaithFlowRun;
use App\Models\FaithFlowUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class AnalyzeFaithFlowSourceTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Analysis Test Church', 'slug' => 'analysis-test-church-'.uniqid()]);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
    }

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

    protected function makeRun(string $sourceText = 'Hope does not put us to shame. Romans 5:5.'): FaithFlowRun
    {
        return FaithFlowRun::factory()->forChurch($this->church)->create([
            'source_text' => $sourceText,
            'source_char_count' => mb_strlen($sourceText),
        ]);
    }

    public function test_successful_analysis_transitions_the_run_and_persists_the_structured_result(): void
    {
        CanonicalAnalysisAgent::fake([$this->validPayload()]);

        $run = $this->makeRun();

        $result = app(AnalyzeFaithFlowSource::class)->handle($run);

        $this->assertSame(FaithFlowRunStatus::ANALYZED, $result->status);
        $this->assertSame('A sermon about hope in difficult seasons.', $result->canonical_analysis['source_summary']);
        $this->assertNull($result->analysis_error);
        $this->assertSame(CanonicalAnalysisAgent::PROMPT_VERSION, $result->prompt_version);
        $this->assertSame(1, $result->analysis_attempts);
    }

    public function test_successful_analysis_records_usage(): void
    {
        CanonicalAnalysisAgent::fake([$this->validPayload()]);

        $run = $this->makeRun();
        app(AnalyzeFaithFlowSource::class)->handle($run);

        $this->assertDatabaseHas('faithflow_usage', [
            'faithflow_run_id' => $run->id,
            'church_id' => $this->church->id,
            'operation' => 'analysis',
            'status' => 'success',
        ]);
    }

    public function test_ungrounded_statements_are_not_persisted(): void
    {
        $payload = $this->validPayload();
        $payload['notable_statements'][] = ['text' => 'This was never actually said.', 'context' => 'fabricated'];
        CanonicalAnalysisAgent::fake([$payload]);

        $run = $this->makeRun('Hope does not put us to shame. Romans 5:5.');
        $result = app(AnalyzeFaithFlowSource::class)->handle($run);

        $this->assertCount(1, $result->canonical_analysis['notable_statements']);
        $this->assertSame('Hope does not put us to shame.', $result->canonical_analysis['notable_statements'][0]['text']);
    }

    public function test_malformed_response_transitions_the_run_to_analysis_failed(): void
    {
        $malformed = $this->validPayload();
        unset($malformed['source_summary']);
        CanonicalAnalysisAgent::fake([$malformed, $malformed]); // both retry attempts malformed

        $run = $this->makeRun();
        $result = app(AnalyzeFaithFlowSource::class)->handle($run);

        $this->assertSame(FaithFlowRunStatus::ANALYSIS_FAILED, $result->status);
        $this->assertNotNull($result->analysis_error);
        $this->assertNull($result->canonical_analysis);
    }

    public function test_malformed_response_records_failed_usage_with_correct_error_category(): void
    {
        $malformed = $this->validPayload();
        unset($malformed['source_summary']);
        CanonicalAnalysisAgent::fake([$malformed, $malformed]);

        $run = $this->makeRun();
        app(AnalyzeFaithFlowSource::class)->handle($run);

        $this->assertDatabaseHas('faithflow_usage', [
            'faithflow_run_id' => $run->id,
            'status' => 'failed',
            'error_category' => 'malformed_response',
        ]);
    }

    public function test_provider_failure_transitions_the_run_to_analysis_failed(): void
    {
        CanonicalAnalysisAgent::fake(function (): never {
            throw new RuntimeException('Simulated provider outage.');
        });

        $run = $this->makeRun();
        $result = app(AnalyzeFaithFlowSource::class)->handle($run);

        $this->assertSame(FaithFlowRunStatus::ANALYSIS_FAILED, $result->status);
        $this->assertDatabaseHas('faithflow_usage', [
            'faithflow_run_id' => $run->id,
            'status' => 'failed',
            'error_category' => 'provider_failure',
        ]);
    }

    public function test_failure_message_never_exposes_raw_exception_detail(): void
    {
        CanonicalAnalysisAgent::fake(function (): never {
            throw new RuntimeException('raw provider secret payload detail');
        });

        $run = $this->makeRun();
        $result = app(AnalyzeFaithFlowSource::class)->handle($run);

        $this->assertStringNotContainsString('raw provider secret payload detail', (string) $result->analysis_error);
    }

    public function test_retry_recovers_from_a_single_transient_failure(): void
    {
        $attempts = 0;
        $payload = $this->validPayload();

        CanonicalAnalysisAgent::fake(function () use (&$attempts, $payload) {
            $attempts++;

            if ($attempts === 1) {
                throw new RuntimeException('Transient failure.');
            }

            return $payload;
        });

        $run = $this->makeRun();
        $result = app(AnalyzeFaithFlowSource::class)->handle($run);

        $this->assertSame(FaithFlowRunStatus::ANALYZED, $result->status);
        $this->assertSame(2, $result->analysis_attempts);
        $this->assertSame(2, FaithFlowUsage::query()->where('faithflow_run_id', $run->id)->count());
    }

    public function test_retry_is_bounded_to_two_attempts(): void
    {
        CanonicalAnalysisAgent::fake(function (): never {
            throw new RuntimeException('Always fails.');
        });

        $run = $this->makeRun();
        $result = app(AnalyzeFaithFlowSource::class)->handle($run);

        $this->assertSame(FaithFlowRunStatus::ANALYSIS_FAILED, $result->status);
        $this->assertSame(2, $result->analysis_attempts);
        $this->assertSame(2, FaithFlowUsage::query()->where('faithflow_run_id', $run->id)->count());
    }

    public function test_analyzing_an_already_analyzed_run_is_idempotent_and_makes_no_new_prompt(): void
    {
        // Exactly one fake response is configured, with stray prompts
        // forbidden — if the second handle() call triggered a real second
        // prompt (i.e. idempotency failed to short-circuit), the exhausted
        // response list would throw rather than silently succeed.
        CanonicalAnalysisAgent::fake([$this->validPayload()])->preventStrayPrompts();

        $run = $this->makeRun();
        $firstResult = app(AnalyzeFaithFlowSource::class)->handle($run);

        $secondResult = app(AnalyzeFaithFlowSource::class)->handle($firstResult);

        $this->assertSame(FaithFlowRunStatus::ANALYZED, $secondResult->status);
        $this->assertSame($firstResult->canonical_analysis, $secondResult->canonical_analysis);
    }

    public function test_analyzing_an_in_progress_run_throws(): void
    {
        $run = $this->makeRun();
        $run->forceFill(['status' => FaithFlowRunStatus::ANALYZING])->save();

        $this->expectException(LogicException::class);

        app(AnalyzeFaithFlowSource::class)->handle($run->fresh());
    }

    public function test_a_previously_failed_run_can_be_reanalyzed(): void
    {
        $run = $this->makeRun();
        $run->forceFill(['status' => FaithFlowRunStatus::ANALYSIS_FAILED, 'analysis_error' => 'prior failure'])->save();

        CanonicalAnalysisAgent::fake([$this->validPayload()]);

        $result = app(AnalyzeFaithFlowSource::class)->handle($run->fresh());

        $this->assertSame(FaithFlowRunStatus::ANALYZED, $result->status);
        $this->assertNull($result->analysis_error);
    }
}
