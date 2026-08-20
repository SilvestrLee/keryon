<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\Enums\FaithFlowRunStatus;
use App\FaithFlow\Actions\EditFaithFlowOutput;
use App\Models\Church;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

/**
 * K-FAITHFLOW-001E §7/§8/§33/§34/§52 — the deterministic, non-AI human
 * editing seam. Editing changes `content` only; `generated_content`,
 * `regeneration_count`, and provider provenance are never touched by an
 * edit.
 */
class EditFaithFlowOutputTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Editing Test Church', 'slug' => 'editing-test-church-'.uniqid()]);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
    }

    protected function analyzedRun(): FaithFlowRun
    {
        return FaithFlowRun::factory()->forChurch($this->church)->create(['status' => FaithFlowRunStatus::ANALYZED]);
    }

    protected function generatedOutput(): FaithFlowOutput
    {
        return FaithFlowOutput::factory()->forRun($this->analyzedRun())->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
            'status' => FaithFlowOutputStatus::GENERATED,
            'generated_content' => 'A',
            'content' => 'A',
        ]);
    }

    public function test_generated_output_can_be_edited(): void
    {
        $output = $this->generatedOutput();

        $result = app(EditFaithFlowOutput::class)->handle($output, 'C');

        $this->assertSame('C', $result->content);
    }

    public function test_editing_leaves_generated_content_and_regeneration_count_untouched(): void
    {
        $output = $this->generatedOutput();

        $result = app(EditFaithFlowOutput::class)->handle($output, 'C');

        $this->assertSame('A', $result->generated_content);
        $this->assertSame(0, $result->regeneration_count);
    }

    public function test_editing_sets_edited_at(): void
    {
        $output = $this->generatedOutput();
        $this->assertNull($output->edited_at);

        $result = app(EditFaithFlowOutput::class)->handle($output, 'C');

        $this->assertNotNull($result->edited_at);
        $this->assertTrue($result->isEdited());
    }

    public function test_full_edit_matrix_matches_the_directive_exactly(): void
    {
        // generated_content = A, content = A, edited_at = null -> edit -> C
        // generated_content = A, content = C, edited_at != null
        $output = $this->generatedOutput();

        $result = app(EditFaithFlowOutput::class)->handle($output, 'C');

        $this->assertSame('A', $result->generated_content);
        $this->assertSame('C', $result->content);
        $this->assertNotNull($result->edited_at);
    }

    public function test_blank_content_is_rejected(): void
    {
        $output = $this->generatedOutput();

        $this->expectException(InvalidArgumentException::class);

        app(EditFaithFlowOutput::class)->handle($output, '   ');
    }

    public function test_failed_output_cannot_be_edited(): void
    {
        $output = FaithFlowOutput::factory()->forRun($this->analyzedRun())->failed()->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
        ]);

        $this->expectException(LogicException::class);

        app(EditFaithFlowOutput::class)->handle($output, 'New content');
    }

    public function test_pending_output_cannot_be_edited(): void
    {
        $output = FaithFlowOutput::factory()->forRun($this->analyzedRun())->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
        ]);

        $this->expectException(LogicException::class);

        app(EditFaithFlowOutput::class)->handle($output, 'New content');
    }

    public function test_generating_output_cannot_be_edited(): void
    {
        $output = $this->generatedOutput();
        $output->forceFill(['status' => FaithFlowOutputStatus::GENERATING])->save();

        $this->expectException(LogicException::class);

        app(EditFaithFlowOutput::class)->handle($output->fresh(), 'New content');
    }

    public function test_approved_output_cannot_be_edited(): void
    {
        $output = $this->generatedOutput();
        $output->forceFill([
            'status' => FaithFlowOutputStatus::APPROVED,
            'approved_at' => now(),
        ])->save();

        $this->expectException(LogicException::class);

        app(EditFaithFlowOutput::class)->handle($output->fresh(), 'New content');
    }
}
