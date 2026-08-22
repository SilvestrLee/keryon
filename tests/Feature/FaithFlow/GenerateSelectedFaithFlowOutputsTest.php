<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\Enums\FaithFlowRunStatus;
use App\FaithFlow\Actions\GenerateSelectedFaithFlowOutputs;
use App\FaithFlow\Ai\StructuredOutputGenerationAgent;
use App\FaithFlow\Ai\TextOutputGenerationAgent;
use App\Models\Church;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class GenerateSelectedFaithFlowOutputsTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Orchestration Test Church', 'slug' => 'orchestration-test-church-'.uniqid()]);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
    }

    protected function analyzedRun(): FaithFlowRun
    {
        return FaithFlowRun::factory()->forChurch($this->church)->create([
            'status' => FaithFlowRunStatus::ANALYZED,
            'canonical_analysis' => [
                'source_summary' => 'A sermon about hope.',
                'principal_message' => 'God remains faithful.',
                'key_themes' => ['hope'],
                'notable_statements' => [],
                'scripture_references' => [],
                'ministry_context' => 'Sunday sermon',
                'audience_clues' => null,
                'tone' => 'Encouraging',
            ],
        ]);
    }

    public function test_run_precondition_is_enforced(): void
    {
        $run = FaithFlowRun::factory()->forChurch($this->church)->create(['status' => FaithFlowRunStatus::DRAFT]);

        $this->expectException(LogicException::class);

        app(GenerateSelectedFaithFlowOutputs::class)->handle($run, [FaithFlowOutputType::DEVOTIONAL]);
    }

    public function test_creates_output_rows_for_selected_types_that_do_not_yet_exist(): void
    {
        TextOutputGenerationAgent::fake(['Generated text.']);
        StructuredOutputGenerationAgent::fake([['themes' => ['hope']]]);

        $run = $this->analyzedRun();

        $this->assertSame(0, FaithFlowOutput::query()->where('faithflow_run_id', $run->id)->count());

        $results = app(GenerateSelectedFaithFlowOutputs::class)->handle($run, [
            FaithFlowOutputType::DEVOTIONAL,
            FaithFlowOutputType::KEY_THEMES,
        ]);

        $this->assertCount(2, $results);
        $this->assertSame(2, FaithFlowOutput::query()->where('faithflow_run_id', $run->id)->count());
    }

    public function test_does_not_duplicate_a_pre_existing_output_row(): void
    {
        TextOutputGenerationAgent::fake(['Generated text.']);

        $run = $this->analyzedRun();
        $existing = FaithFlowOutput::factory()->forRun($run)->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);

        app(GenerateSelectedFaithFlowOutputs::class)->handle($run, [FaithFlowOutputType::DEVOTIONAL]);

        $this->assertSame(1, FaithFlowOutput::query()->where('faithflow_run_id', $run->id)->count());
        $this->assertSame(FaithFlowOutputStatus::GENERATED, $existing->fresh()->status);
    }

    public function test_partial_failure_does_not_poison_successful_outputs(): void
    {
        $run = $this->analyzedRun();

        // DEVOTIONAL (text-shaped) succeeds; KEY_THEMES and SOCIAL_CAPTIONS
        // (both list-shaped, sharing StructuredOutputGenerationAgent) both
        // fail — proving one output's failure never poisons a sibling
        // output generated through a completely different Agent class, and
        // that two failing outputs in the same batch are each still
        // recorded and retried independently.
        TextOutputGenerationAgent::fake(['A generated devotional.']);
        StructuredOutputGenerationAgent::fake(function (): never {
            throw new RuntimeException('Simulated failure for this output type.');
        });

        $results = app(GenerateSelectedFaithFlowOutputs::class)->handle($run, [
            FaithFlowOutputType::DEVOTIONAL,
            FaithFlowOutputType::KEY_THEMES,
            FaithFlowOutputType::SOCIAL_CAPTIONS,
        ]);

        $byType = collect($results)->keyBy(fn (FaithFlowOutput $output) => $output->output_type->value);

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $byType[FaithFlowOutputType::DEVOTIONAL->value]->status);
        $this->assertSame(FaithFlowOutputStatus::FAILED, $byType[FaithFlowOutputType::KEY_THEMES->value]->status);
        $this->assertSame(FaithFlowOutputStatus::FAILED, $byType[FaithFlowOutputType::SOCIAL_CAPTIONS->value]->status);

        // The successful output's content survives the batch regardless of
        // the other two outputs' failures — the run/canonical analysis
        // itself remains untouched and valid throughout.
        $this->assertSame('A generated devotional.', $byType[FaithFlowOutputType::DEVOTIONAL->value]->generated_content);
        $this->assertSame(FaithFlowRunStatus::ANALYZED, $run->fresh()->status);
    }

    public function test_usage_is_recorded_for_every_attempt_including_failures(): void
    {
        $run = $this->analyzedRun();

        TextOutputGenerationAgent::fake(['Generated text.']);
        StructuredOutputGenerationAgent::fake(function (): never {
            throw new RuntimeException('Always fails.');
        });

        app(GenerateSelectedFaithFlowOutputs::class)->handle($run, [
            FaithFlowOutputType::DEVOTIONAL,
            FaithFlowOutputType::KEY_THEMES,
        ]);

        $this->assertDatabaseHas('faithflow_usage', [
            'faithflow_run_id' => $run->id,
            'operation' => 'generate',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('faithflow_usage', [
            'faithflow_run_id' => $run->id,
            'operation' => 'generate',
            'status' => 'failed',
        ]);
    }
}
