<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\Enums\FaithFlowRunStatus;
use App\FaithFlow\Actions\RegenerateFaithFlowOutput;
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

class RegenerateFaithFlowOutputTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Regeneration Test Church', 'slug' => 'regeneration-test-church-'.uniqid()]);
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

    protected function generatedOutput(?string $content = 'A', ?string $editedAt = null): FaithFlowOutput
    {
        return FaithFlowOutput::factory()->forRun($this->analyzedRun())->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
            'status' => FaithFlowOutputStatus::GENERATED,
            'generated_content' => $content,
            'content' => $content,
            'edited_at' => $editedAt,
        ]);
    }

    // --- §61: the human-safety preservation matrix, exactly ---

    public function test_regenerating_an_unedited_output_replaces_both_generated_content_and_content(): void
    {
        TextOutputGenerationAgent::fake(['B']);

        $output = $this->generatedOutput(content: 'A');
        $result = app(RegenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame('B', $result->generated_content);
        $this->assertSame('B', $result->content);
    }

    public function test_regenerating_a_human_edited_output_preserves_content_and_only_replaces_generated_content(): void
    {
        TextOutputGenerationAgent::fake(['B']);

        $output = $this->generatedOutput(content: 'A', editedAt: now());
        $output->forceFill(['content' => 'human-edited C'])->save();

        $result = app(RegenerateFaithFlowOutput::class)->handle($output->fresh());

        $this->assertSame('B', $result->generated_content);
        $this->assertSame('human-edited C', $result->content);
    }

    // --- §28: regeneration failure must not destroy previously usable content ---

    public function test_failed_regeneration_preserves_the_prior_content_and_reverts_to_generated(): void
    {
        TextOutputGenerationAgent::fake(function (): never {
            throw new RuntimeException('Simulated provider outage.');
        });

        $output = $this->generatedOutput(content: 'A');
        $result = app(RegenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $result->status);
        $this->assertSame('A', $result->generated_content);
        $this->assertSame('A', $result->content);
        $this->assertNotNull($result->error_message);
    }

    public function test_failed_regeneration_of_a_human_edited_output_preserves_the_edit(): void
    {
        TextOutputGenerationAgent::fake(function (): never {
            throw new RuntimeException('Simulated provider outage.');
        });

        $output = $this->generatedOutput(content: 'A', editedAt: now());
        $output->forceFill(['content' => 'human-edited C'])->save();

        $result = app(RegenerateFaithFlowOutput::class)->handle($output->fresh());

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $result->status);
        $this->assertSame('A', $result->generated_content);
        $this->assertSame('human-edited C', $result->content);
    }

    public function test_a_failed_regeneration_never_transitions_to_the_failed_status(): void
    {
        // Confirms the FAILED <=> "no usable content ever existed" invariant
        // FaithFlowOutput::canBeApproved() relies on stays true.
        TextOutputGenerationAgent::fake(function (): never {
            throw new RuntimeException('Always fails.');
        });

        $output = $this->generatedOutput(content: 'A');
        $result = app(RegenerateFaithFlowOutput::class)->handle($output);

        $this->assertNotSame(FaithFlowOutputStatus::FAILED, $result->status);
        $this->assertTrue($result->canBeApproved());
    }

    // --- regeneration_count / usage ---

    public function test_regeneration_increments_regeneration_count(): void
    {
        TextOutputGenerationAgent::fake(['B']);

        $output = $this->generatedOutput(content: 'A');
        $this->assertSame(0, $output->fresh()->regeneration_count);

        $result = app(RegenerateFaithFlowOutput::class)->handle($output);

        $this->assertSame(1, $result->regeneration_count);
    }

    public function test_regeneration_records_usage_with_the_regenerate_operation(): void
    {
        TextOutputGenerationAgent::fake(['B']);

        $output = $this->generatedOutput(content: 'A');
        app(RegenerateFaithFlowOutput::class)->handle($output);

        $this->assertDatabaseHas('faithflow_usage', [
            'faithflow_output_id' => $output->id,
            'operation' => 'regenerate',
            'status' => 'success',
        ]);
    }

    // --- entry guards / idempotency-safety (§29) ---

    public function test_cannot_regenerate_a_pending_output(): void
    {
        $output = FaithFlowOutput::factory()->forRun($this->analyzedRun())->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
        ]);

        $this->expectException(LogicException::class);

        app(RegenerateFaithFlowOutput::class)->handle($output);
    }

    public function test_cannot_regenerate_a_failed_output(): void
    {
        $output = FaithFlowOutput::factory()->forRun($this->analyzedRun())->failed()->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
        ]);

        $this->expectException(LogicException::class);

        app(RegenerateFaithFlowOutput::class)->handle($output);
    }

    public function test_cannot_regenerate_an_already_generating_output(): void
    {
        $output = $this->generatedOutput(content: 'A');
        $output->forceFill(['status' => FaithFlowOutputStatus::GENERATING])->save();

        $this->expectException(LogicException::class);

        app(RegenerateFaithFlowOutput::class)->handle($output->fresh());
    }
}
