<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\ContentOrigin;
use App\Enums\FaithFlowOutputType;
use App\Enums\FaithFlowRunStatus;
use App\Filament\Pages\FaithFlow;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-FAITHFLOW-001F §74 — mapped outputs show successful Content Studio
 * handoff; reference outputs (Key Themes/Key Quotes) never claim a
 * Content Studio destination they don't have.
 */
class FaithFlowHandoffUiTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->church = Church::create(['name' => 'Handoff UI Church', 'slug' => 'ff-handoff-ui-church']);
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

    public function test_approving_a_mapped_output_creates_a_content_item_and_links_it(): void
    {
        $run = $this->analyzedRun();
        $output = FaithFlowOutput::factory()->forRun($run)->generated()->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
            'content' => 'The final devotional text.',
        ]);

        Livewire::test(FaithFlow::class, ['run' => $run->id])->call('approveOutput', $output->id);

        $output->refresh();
        $this->assertNotNull($output->content_item_id);
        $this->assertSame(ContentOrigin::FAITHFLOW, $output->contentItem->origin);
        $this->assertSame('The final devotional text.', $output->contentItem->body);
    }

    public function test_approved_mapped_output_shows_the_content_studio_relationship(): void
    {
        $run = $this->analyzedRun();
        $output = FaithFlowOutput::factory()->forRun($run)->generated()->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);

        $component = Livewire::test(FaithFlow::class, ['run' => $run->id]);
        $component->call('approveOutput', $output->id);
        $component->set('activeOutputId', $output->id);

        $component->assertSee('Content Studio');
    }

    public function test_approving_key_themes_does_not_create_a_content_item(): void
    {
        $run = $this->analyzedRun();
        $output = FaithFlowOutput::factory()->forRun($run)->generated()->create(['output_type' => FaithFlowOutputType::KEY_THEMES]);

        Livewire::test(FaithFlow::class, ['run' => $run->id])->call('approveOutput', $output->id);

        $this->assertNull($output->fresh()->content_item_id);
        $this->assertSame(0, ContentItem::query()->count());
    }

    public function test_key_themes_approve_button_says_mark_reviewed_not_send_to_content_studio(): void
    {
        $run = $this->analyzedRun();
        $output = FaithFlowOutput::factory()->forRun($run)->generated()->create(['output_type' => FaithFlowOutputType::KEY_THEMES]);

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->set('activeOutputId', $output->id)
            ->assertSee('Mark Reviewed')
            ->assertDontSee('Send to Content Studio');
    }

    public function test_mapped_output_approve_button_says_send_to_content_studio(): void
    {
        $run = $this->analyzedRun();
        $output = FaithFlowOutput::factory()->forRun($run)->generated()->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);

        Livewire::test(FaithFlow::class, ['run' => $run->id])
            ->set('activeOutputId', $output->id)
            ->assertSee('Send to Content Studio');
    }

    public function test_repeated_approval_never_duplicates_the_content_item(): void
    {
        $run = $this->analyzedRun();
        $output = FaithFlowOutput::factory()->forRun($run)->generated()->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);

        $component = Livewire::test(FaithFlow::class, ['run' => $run->id]);
        $component->call('approveOutput', $output->id);
        $component->call('approveOutput', $output->id);

        $this->assertSame(1, ContentItem::query()->count());
    }
}
