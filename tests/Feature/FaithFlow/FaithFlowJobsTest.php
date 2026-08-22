<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\Enums\FaithFlowRunStatus;
use App\FaithFlow\Ai\CanonicalAnalysisAgent;
use App\FaithFlow\Ai\TextOutputGenerationAgent;
use App\Jobs\FaithFlow\AnalyzeFaithFlowSourceJob;
use App\Jobs\FaithFlow\GenerateFaithFlowOutputJob;
use App\Jobs\FaithFlow\RegenerateFaithFlowOutputJob;
use App\Models\Church;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * K-FAITHFLOW-001F §6/§16/§17 — proves each FaithFlow Job correctly
 * restores tenant context (via the K-ASYNC-001 foundation, exercised
 * directly through TenantAwareJob::handle()) and invokes the existing,
 * unmodified domain Action — no real AI provider call, deterministic
 * Agent fakes only (§54).
 */
class FaithFlowJobsTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Jobs Test Church', 'slug' => 'ff-jobs-test-church']);
        $this->user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
    }

    public function test_analyze_job_invokes_the_canonical_analysis_action(): void
    {
        CanonicalAnalysisAgent::fake([[
            'source_summary' => 'A sermon about hope.',
            'principal_message' => 'God is faithful.',
            'key_themes' => ['hope'],
            'notable_statements' => [],
            'scripture_references' => [],
            'ministry_context' => 'Sunday sermon',
            'audience_clues' => null,
            'tone' => 'Encouraging',
        ]]);

        $run = FaithFlowRun::factory()->forChurch($this->church)->create();
        $context = new \App\Support\TenantExecutionContext($this->church->id, $this->user->id);

        (new AnalyzeFaithFlowSourceJob($context, $run->id))->handle(app(TenantContext::class));

        $this->assertSame(FaithFlowRunStatus::ANALYZED, $run->fresh()->status);
    }

    public function test_generate_job_invokes_the_generate_action(): void
    {
        TextOutputGenerationAgent::fake(['A generated devotional.']);

        $run = FaithFlowRun::factory()->forChurch($this->church)->create([
            'status' => FaithFlowRunStatus::ANALYZED,
            'canonical_analysis' => [
                'source_summary' => 'x', 'principal_message' => 'x', 'key_themes' => [],
                'notable_statements' => [], 'scripture_references' => [],
                'ministry_context' => 'x', 'audience_clues' => null, 'tone' => 'x',
            ],
        ]);
        $output = FaithFlowOutput::factory()->forRun($run)->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);
        $context = new \App\Support\TenantExecutionContext($this->church->id, $this->user->id);

        (new GenerateFaithFlowOutputJob($context, $output->id))->handle(app(TenantContext::class));

        $this->assertSame(FaithFlowOutputStatus::GENERATED, $output->fresh()->status);
        $this->assertSame('A generated devotional.', $output->fresh()->content);
    }

    public function test_regenerate_job_invokes_the_regenerate_action(): void
    {
        TextOutputGenerationAgent::fake(['A new devotional.']);

        $run = FaithFlowRun::factory()->forChurch($this->church)->create(['status' => FaithFlowRunStatus::ANALYZED]);
        $output = FaithFlowOutput::factory()->forRun($run)->generated()->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
            'content' => 'Old text.',
            'generated_content' => 'Old text.',
        ]);
        $context = new \App\Support\TenantExecutionContext($this->church->id, $this->user->id);

        (new RegenerateFaithFlowOutputJob($context, $output->id))->handle(app(TenantContext::class));

        $this->assertSame('A new devotional.', $output->fresh()->content);
        $this->assertSame(1, $output->fresh()->regeneration_count);
    }

    /**
     * §61 — a job must never trust a caller-supplied Church ID; it only
     * ever restores from the captured TenantExecutionContext and
     * re-fetches the target through the resulting tenant-scoped query.
     */
    public function test_generate_job_re_fetches_the_target_through_the_restored_tenant_scope(): void
    {
        $otherChurch = Church::create(['name' => 'Other Jobs Church', 'slug' => 'ff-jobs-other-church']);
        $run = FaithFlowRun::factory()->forChurch($otherChurch)->create(['status' => FaithFlowRunStatus::ANALYZED]);
        $output = FaithFlowOutput::factory()->forRun($run)->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);

        // Context claims this->church, but the output actually belongs to
        // $otherChurch — the tenant-scoped findOrFail() inside execute()
        // must not find it.
        $context = new \App\Support\TenantExecutionContext($this->church->id, $this->user->id);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        (new GenerateFaithFlowOutputJob($context, $output->id))->handle(app(TenantContext::class));
    }
}
