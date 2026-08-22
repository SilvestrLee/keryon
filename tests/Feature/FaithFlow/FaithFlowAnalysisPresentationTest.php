<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowRunStatus;
use App\Filament\Pages\FaithFlow;
use App\Models\Church;
use App\Models\FaithFlowRun;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-FAITHFLOW-001F §20/§21/§70 — the canonical analysis is presented as
 * human-readable "understanding," visually distinct from generated
 * output. Tests important product semantics (the right data reaches the
 * view), not brittle HTML snapshots.
 */
class FaithFlowAnalysisPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->church = Church::create(['name' => 'Analysis Presentation Church', 'slug' => 'ff-analysis-presentation-church']);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
    }

    protected function analyzedRun(): FaithFlowRun
    {
        return FaithFlowRun::factory()->forChurch($this->church)->create([
            'status' => FaithFlowRunStatus::ANALYZED,
            'canonical_analysis' => [
                'source_summary' => 'A sermon about persevering hope.',
                'principal_message' => 'God remains faithful even in hardship.',
                'key_themes' => ['hope', 'perseverance'],
                'notable_statements' => [['text' => 'Hope does not put us to shame.', 'context' => 'Central exhortation']],
                'scripture_references' => [['reference' => 'Romans 5:5', 'context' => 'Quoted directly']],
                'ministry_context' => 'Sunday sermon',
                'audience_clues' => 'General congregation',
                'tone' => 'Encouraging',
            ],
        ]);
    }

    public function test_analyzed_run_renders_the_central_message(): void
    {
        $run = $this->analyzedRun();

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->assertSee('God remains faithful even in hardship.')
            ->assertSee('Understanding your source');
    }

    public function test_analyzed_run_renders_key_themes(): void
    {
        $run = $this->analyzedRun();

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->assertSee('hope')
            ->assertSee('perseverance');
    }

    public function test_analyzed_run_renders_scripture_references(): void
    {
        $run = $this->analyzedRun();

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->assertSee('Romans 5:5');
    }

    public function test_analysis_section_is_not_shown_before_analysis_completes(): void
    {
        $run = FaithFlowRun::factory()->forChurch($this->church)->create(['status' => FaithFlowRunStatus::DRAFT]);

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->assertDontSee('Understanding your source');
    }

    public function test_analyzing_run_shows_processing_copy_not_raw_technical_status(): void
    {
        $run = FaithFlowRun::factory()->forChurch($this->church)->analyzing()->create();

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->assertSee('Reading your source')
            ->assertDontSee('ANALYZING');
    }

    public function test_analysis_failure_preserves_and_shows_the_source_text(): void
    {
        $run = FaithFlowRun::factory()->forChurch($this->church)->analysisFailed()->create([
            'source_text' => 'The original sermon notes that must survive a failed analysis attempt.',
        ]);

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->assertSee('The original sermon notes that must survive a failed analysis attempt.')
            ->assertSee("Analysis didn");
    }
}
