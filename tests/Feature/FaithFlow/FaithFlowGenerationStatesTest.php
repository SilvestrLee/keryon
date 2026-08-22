<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowOutputStatus;
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
 * K-FAITHFLOW-001F §18/§25/§62/§72 — per-output async-state presentation
 * and partial-failure behavior: one output failing must never hide or
 * block access to the others.
 */
class FaithFlowGenerationStatesTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->church = Church::create(['name' => 'Generation States Church', 'slug' => 'ff-generation-states-church']);
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

    public function test_pending_output_shows_generating_state(): void
    {
        $run = $this->analyzedRun();
        $output = FaithFlowOutput::factory()->forRun($run)->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->set('activeOutputId', $output->id)
            ->assertSee('Generating');
    }

    public function test_failed_output_shows_retry_action(): void
    {
        $run = $this->analyzedRun();
        $output = FaithFlowOutput::factory()->forRun($run)->failed()->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->set('activeOutputId', $output->id)
            ->assertSee('Generation failed')
            ->assertSee('Retry');
    }

    public function test_generated_output_shows_content_and_actions(): void
    {
        $run = $this->analyzedRun();
        $output = FaithFlowOutput::factory()->forRun($run)->generated()->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
            'content' => 'A finished devotional reflection.',
        ]);

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->set('activeOutputId', $output->id)
            ->assertSee('A finished devotional reflection.')
            ->assertSee('Edit')
            ->assertSee('Regenerate');
    }

    public function test_partial_failure_does_not_hide_the_successful_outputs(): void
    {
        $run = $this->analyzedRun();
        $devotional = FaithFlowOutput::factory()->forRun($run)->generated()->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
            'content' => 'A finished devotional.',
        ]);
        $prayerPoints = FaithFlowOutput::factory()->forRun($run)->failed()->create([
            'output_type' => FaithFlowOutputType::PRAYER_POINTS,
        ]);

        $component = Livewire::test(FaithFlow::class, ['run' => $run->id]);

        $component->assertSee($devotional->output_type->label());
        $component->assertSee($prayerPoints->output_type->label());

        // Switching to the failed output does not remove the ability to
        // switch back to (and read) the successful one.
        $component->set('activeOutputId', $devotional->id)->assertSee('A finished devotional.');
        $component->set('activeOutputId', $prayerPoints->id)->assertSee('Retry');
        $component->set('activeOutputId', $devotional->id)->assertSee('A finished devotional.');
    }

    public function test_retry_only_dispatches_for_the_failed_output(): void
    {
        Queue::fake();
        $run = $this->analyzedRun();
        $devotional = FaithFlowOutput::factory()->forRun($run)->generated()->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);
        $prayerPoints = FaithFlowOutput::factory()->forRun($run)->failed()->create(['output_type' => FaithFlowOutputType::PRAYER_POINTS]);

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->call('retryOutput', $prayerPoints->id);

        Queue::assertPushed(GenerateFaithFlowOutputJob::class, function (GenerateFaithFlowOutputJob $job) use ($prayerPoints) {
            return $job->outputId === $prayerPoints->id;
        });
        Queue::assertNotPushed(GenerateFaithFlowOutputJob::class, function (GenerateFaithFlowOutputJob $job) use ($devotional) {
            return $job->outputId === $devotional->id;
        });
    }

    public function test_generating_state_does_not_show_edit_or_approve_actions(): void
    {
        $run = $this->analyzedRun();
        $output = FaithFlowOutput::factory()->forRun($run)->generating()->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->set('activeOutputId', $output->id)
            ->assertDontSee('Approve')
            ->assertDontSee('data-ff-edit');
    }
}
