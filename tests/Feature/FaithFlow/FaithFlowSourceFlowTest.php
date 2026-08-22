<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowRunStatus;
use App\Filament\Pages\FaithFlow;
use App\Jobs\FaithFlow\AnalyzeFaithFlowSourceJob;
use App\Models\Church;
use App\Models\FaithFlowRun;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-FAITHFLOW-001F §69 — source creation and the analysis dispatch
 * contract. Uses Queue::fake() to prove *what* gets dispatched and with
 * *what* context, without spawning a real worker in every feature test —
 * the worker foundation itself is already proven by K-ASYNC-001/R1.
 */
class FaithFlowSourceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->church = Church::create(['name' => 'Source Flow Church', 'slug' => 'ff-source-flow-church']);
        $this->user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->user);
    }

    protected function longSourceText(): string
    {
        return str_repeat('This is a sample sermon sentence about hope and faith. ', 5);
    }

    public function test_creating_a_source_persists_a_faithflow_run(): void
    {
        Livewire::test(FaithFlow::class)
            ->set('sourceText', $this->longSourceText())
            ->call('createSource');

        $this->assertDatabaseHas('faithflow_runs', [
            'church_id' => $this->church->id,
            'status' => FaithFlowRunStatus::DRAFT->value,
        ]);
    }

    public function test_source_is_persisted_before_any_analysis_is_attempted(): void
    {
        Queue::fake();

        Livewire::test(FaithFlow::class)
            ->set('sourceText', $this->longSourceText())
            ->call('createSource');

        Queue::assertNothingPushed();
        $this->assertSame(1, FaithFlowRun::query()->count());
    }

    public function test_too_short_source_is_rejected_with_entered_text_preserved(): void
    {
        Livewire::test(FaithFlow::class)
            ->set('sourceText', 'Too short.')
            ->call('createSource')
            ->assertHasErrors(['sourceText']);

        $this->assertSame(0, FaithFlowRun::query()->count());
    }

    public function test_analyze_dispatches_the_analysis_job_with_the_correct_context(): void
    {
        Queue::fake();

        $run = FaithFlowRun::create([
            'source_text' => $this->longSourceText(),
            'source_char_count' => mb_strlen($this->longSourceText()),
        ]);

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->call('analyze');

        Queue::assertPushed(AnalyzeFaithFlowSourceJob::class, function (AnalyzeFaithFlowSourceJob $job) use ($run) {
            return $job->runId === $run->id
                && $job->context->churchId === $this->church->id
                && $job->context->actorUserId === $this->user->id;
        });
    }

    public function test_analyze_does_not_dispatch_for_a_run_that_is_already_analyzing(): void
    {
        Queue::fake();

        $run = FaithFlowRun::create([
            'source_text' => $this->longSourceText(),
            'source_char_count' => mb_strlen($this->longSourceText()),
        ]);
        $run->forceFill(['status' => FaithFlowRunStatus::ANALYZING])->save();

        $this->expectException(\LogicException::class);

        Livewire::test(FaithFlow::class, ['run' => $run->id])->call('analyze');
    }

    /**
     * Retrying a failed analysis dispatches a fresh job rather than
     * duplicating the run itself — the run row is reused throughout.
     */
    public function test_retry_after_analysis_failure_does_not_duplicate_the_run(): void
    {
        Queue::fake();

        $run = FaithFlowRun::factory()->forChurch($this->church)->analysisFailed()->create();

        Livewire::test(FaithFlow::class, ['run' => $run->id])->call('analyze');

        $this->assertSame(1, FaithFlowRun::query()->count());
        Queue::assertPushed(AnalyzeFaithFlowSourceJob::class, 1);
    }
}
