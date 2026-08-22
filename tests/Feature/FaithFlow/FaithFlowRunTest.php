<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowRunStatus;
use App\Models\Church;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\FaithFlowUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaithFlowRunTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Run Test Church', 'slug' => 'run-test-church-'.uniqid()]);

        // FaithFlowRun/FaithFlowOutput/FaithFlowUsage all use BelongsToChurch,
        // whose global scope fails closed without an active TenantContext —
        // an authenticated member of the same church is required for any
        // relationship query in these tests, exactly like Content Studio's
        // own model tests.
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
    }

    public function test_run_belongs_to_church(): void
    {
        $run = FaithFlowRun::factory()->forChurch($this->church)->create();

        $this->assertTrue($run->church->is($this->church));
    }

    public function test_run_has_many_outputs(): void
    {
        $run = FaithFlowRun::factory()->forChurch($this->church)->create();
        $outputA = FaithFlowOutput::factory()->forRun($run)->create();
        $outputB = FaithFlowOutput::factory()->forRun($run)->create(['output_type' => 'prayer_points']);

        $ids = $run->outputs()->pluck('id')->all();

        $this->assertContains($outputA->id, $ids);
        $this->assertContains($outputB->id, $ids);
    }

    public function test_run_has_many_usage_records(): void
    {
        $run = FaithFlowRun::factory()->forChurch($this->church)->create();
        $usage = FaithFlowUsage::factory()->forRun($run)->create();

        $this->assertTrue($run->usage()->first()->is($usage));
    }

    public function test_run_belongs_to_creator(): void
    {
        $user = User::factory()->forChurch($this->church)->create();
        $run = FaithFlowRun::factory()->forChurch($this->church)->create(['created_by' => $user->id]);

        $this->assertTrue($run->creator->is($user));
    }

    public function test_status_casts_to_enum(): void
    {
        $run = FaithFlowRun::factory()->forChurch($this->church)->analyzed()->create();

        $this->assertSame(FaithFlowRunStatus::ANALYZED, $run->status);
    }

    public function test_canonical_analysis_defaults_to_null_and_is_never_populated_artificially(): void
    {
        $run = FaithFlowRun::factory()->forChurch($this->church)->create();

        $this->assertNull($run->canonical_analysis);
    }

    public function test_canonical_analysis_casts_to_array_when_present(): void
    {
        $run = FaithFlowRun::factory()->forChurch($this->church)->create();
        $run->forceFill(['canonical_analysis' => ['summary' => 'Placeholder.']])->save();

        $this->assertSame(['summary' => 'Placeholder.'], $run->fresh()->canonical_analysis);
    }

    public function test_run_is_soft_deletable(): void
    {
        $run = FaithFlowRun::factory()->forChurch($this->church)->create();
        $id = $run->id;

        $run->delete();

        $this->assertSoftDeleted('faithflow_runs', ['id' => $id]);
    }

    public function test_deleting_church_cascades_to_runs(): void
    {
        $run = FaithFlowRun::factory()->forChurch($this->church)->create();

        $this->church->delete();

        $this->assertDatabaseMissing('faithflow_runs', ['id' => $run->id]);
    }

    public function test_deleting_creator_preserves_the_run_and_nulls_attribution(): void
    {
        $user = User::factory()->create();
        $run = FaithFlowRun::factory()->forChurch($this->church)->create(['created_by' => $user->id]);

        $user->delete();

        $this->assertDatabaseHas('faithflow_runs', ['id' => $run->id, 'created_by' => null]);
    }
}
