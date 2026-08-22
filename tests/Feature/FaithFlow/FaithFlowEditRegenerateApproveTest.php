<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\Enums\FaithFlowRunStatus;
use App\Filament\Pages\FaithFlow;
use App\Jobs\FaithFlow\RegenerateFaithFlowOutputJob;
use App\Models\Church;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-FAITHFLOW-001F §73 — proving UI wiring boundaries onto the existing,
 * already-domain-tested Edit/Regenerate/Approve Actions. Does not
 * duplicate 001D/001E's own exhaustive domain coverage.
 */
class FaithFlowEditRegenerateApproveTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->church = Church::create(['name' => 'Edit Approve Church', 'slug' => 'ff-edit-approve-church']);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
    }

    protected function analyzedRun(): FaithFlowRun
    {
        return FaithFlowRun::factory()->forChurch($this->church)->create([
            'status' => FaithFlowRunStatus::ANALYZED,
            'canonical_analysis' => [
                'source_summary' => 'A sermon about hope.',
                'principal_message' => 'God is faithful.',
                'key_themes' => ['hope'],
                'notable_statements' => [],
                'scripture_references' => [],
                'ministry_context' => 'Sunday sermon',
                'audience_clues' => null,
                'tone' => 'Encouraging',
            ],
        ]);
    }

    protected function generatedOutput(FaithFlowRun $run, FaithFlowOutputType $type = FaithFlowOutputType::DEVOTIONAL): FaithFlowOutput
    {
        return FaithFlowOutput::factory()->forRun($run)->generated()->create([
            'output_type' => $type,
            'content' => 'Original generated text.',
            'generated_content' => 'Original generated text.',
        ]);
    }

    public function test_saving_an_edit_updates_content_and_preserves_generated_content(): void
    {
        $run = $this->analyzedRun();
        $output = $this->generatedOutput($run);

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->call('startEditing', $output->id)
            ->set('editingContent', 'The human-edited version.')
            ->call('saveEdit', $output->id);

        $output->refresh();
        $this->assertSame('The human-edited version.', $output->content);
        $this->assertSame('Original generated text.', $output->generated_content);
        $this->assertTrue($output->isEdited());
    }

    public function test_saving_a_blank_edit_is_rejected(): void
    {
        $run = $this->analyzedRun();
        $output = $this->generatedOutput($run);

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->call('startEditing', $output->id)
            ->set('editingContent', '   ')
            ->call('saveEdit', $output->id);

        $output->refresh();
        $this->assertSame('Original generated text.', $output->content);
    }

    public function test_approved_output_cannot_be_edited(): void
    {
        $run = $this->analyzedRun();
        $output = FaithFlowOutput::factory()->forRun($run)->approved()->create(['output_type' => FaithFlowOutputType::KEY_THEMES]);

        $this->expectException(\LogicException::class);

        Livewire::test(FaithFlow::class, ['run' => $run->id])->call('startEditing', $output->id);
    }

    public function test_regenerate_dispatches_the_regeneration_job(): void
    {
        Queue::fake();
        $run = $this->analyzedRun();
        $output = $this->generatedOutput($run);

        Livewire::test(FaithFlow::class, ['run' => $run->id])->call('regenerateOutput', $output->id);

        Queue::assertPushed(RegenerateFaithFlowOutputJob::class, function (RegenerateFaithFlowOutputJob $job) use ($output) {
            return $job->outputId === $output->id;
        });
    }

    public function test_approve_marks_a_generated_output_approved(): void
    {
        $run = $this->analyzedRun();
        $output = $this->generatedOutput($run, FaithFlowOutputType::DEVOTIONAL);

        Livewire::test(FaithFlow::class, ['run' => $run->id])->call('approveOutput', $output->id);

        $this->assertSame(FaithFlowOutputStatus::APPROVED, $output->fresh()->status);
    }

    public function test_approve_records_the_authenticated_user_as_approver(): void
    {
        $run = $this->analyzedRun();
        $output = $this->generatedOutput($run, FaithFlowOutputType::DEVOTIONAL);
        $user = auth()->user();

        Livewire::test(FaithFlow::class, ['run' => $run->id])->call('approveOutput', $output->id);

        $this->assertSame($user->id, $output->fresh()->approved_by);
    }

    /**
     * approveOutput() deliberately catches the domain guard's
     * LogicException and shows a calm notification instead of letting a
     * raw exception surface — matches saveEdit()'s own graceful-failure
     * pattern (§27's "calm, specific language" instruction).
     */
    public function test_approving_a_pending_output_is_rejected(): void
    {
        $run = $this->analyzedRun();
        $output = FaithFlowOutput::factory()->forRun($run)->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);

        Livewire::test(FaithFlow::class, ['run' => $run->id])->call('approveOutput', $output->id);

        $this->assertSame(FaithFlowOutputStatus::PENDING, $output->fresh()->status);
        $this->assertNull($output->fresh()->approved_by);
    }
}
