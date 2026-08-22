<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowOutputType;
use App\Enums\FaithFlowRunStatus;
use App\Filament\Pages\FaithFlow;
use App\Jobs\FaithFlow\GenerateFaithFlowOutputJob;
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
 * K-FAITHFLOW-001F §71 — output selection and multi-output dispatch.
 */
class FaithFlowOutputSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->church = Church::create(['name' => 'Output Selection Church', 'slug' => 'ff-output-selection-church']);
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

    public function test_all_eight_output_types_are_available_for_selection(): void
    {
        $run = $this->analyzedRun();

        $component = Livewire::test(FaithFlow::class, ['run' => $run->id]);

        foreach (FaithFlowOutputType::cases() as $type) {
            $component->assertSee($type->label());
        }
    }

    public function test_selected_types_create_rows_and_dispatch_jobs(): void
    {
        Queue::fake();
        $run = $this->analyzedRun();

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->set('selectedOutputTypes', [FaithFlowOutputType::DEVOTIONAL->value, FaithFlowOutputType::PRAYER_POINTS->value])
            ->call('generateSelected');

        $this->assertSame(2, FaithFlowOutput::query()->where('faithflow_run_id', $run->id)->count());
        Queue::assertPushed(GenerateFaithFlowOutputJob::class, 2);
    }

    public function test_unselected_types_do_not_create_rows(): void
    {
        Queue::fake();
        $run = $this->analyzedRun();

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->set('selectedOutputTypes', [FaithFlowOutputType::DEVOTIONAL->value])
            ->call('generateSelected');

        $this->assertSame(1, FaithFlowOutput::query()->where('faithflow_run_id', $run->id)->count());
        $this->assertDatabaseMissing('faithflow_outputs', [
            'faithflow_run_id' => $run->id,
            'output_type' => FaithFlowOutputType::PRAYER_POINTS->value,
        ]);
    }

    public function test_calling_generate_selected_twice_does_not_duplicate_rows(): void
    {
        Queue::fake();
        $run = $this->analyzedRun();

        $component = Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->set('selectedOutputTypes', [FaithFlowOutputType::DEVOTIONAL->value]);

        $component->call('generateSelected');
        $component->call('generateSelected');

        $this->assertSame(1, FaithFlowOutput::query()->where('faithflow_run_id', $run->id)->count());
    }

    public function test_no_types_selected_shows_a_warning_and_dispatches_nothing(): void
    {
        Queue::fake();
        $run = $this->analyzedRun();

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->set('selectedOutputTypes', [])
            ->call('generateSelected');

        Queue::assertNothingPushed();
        $this->assertSame(0, FaithFlowOutput::query()->count());
    }

    public function test_generation_dispatch_captures_the_correct_tenant_context(): void
    {
        Queue::fake();
        $run = $this->analyzedRun();
        $user = auth()->user();

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->set('selectedOutputTypes', [FaithFlowOutputType::DEVOTIONAL->value])
            ->call('generateSelected');

        Queue::assertPushed(GenerateFaithFlowOutputJob::class, function (GenerateFaithFlowOutputJob $job) use ($user) {
            return $job->context->churchId === $this->church->id
                && $job->context->actorUserId === $user->id;
        });
    }
}
