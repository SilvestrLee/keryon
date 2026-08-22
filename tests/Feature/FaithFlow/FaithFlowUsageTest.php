<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\FaithFlowUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaithFlowUsageTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Usage Test Church', 'slug' => 'usage-test-church-'.uniqid()]);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
    }

    protected function makeRun(): FaithFlowRun
    {
        return FaithFlowRun::factory()->forChurch($this->church)->create();
    }

    public function test_usage_belongs_to_run(): void
    {
        $run = $this->makeRun();
        $usage = FaithFlowUsage::factory()->forRun($run)->create();

        $this->assertTrue($usage->run->is($run));
    }

    public function test_usage_output_link_is_nullable_for_analysis_level_operations(): void
    {
        $usage = FaithFlowUsage::factory()->forRun($this->makeRun())->create(['operation' => 'analysis']);

        $this->assertNull($usage->faithflow_output_id);
    }

    public function test_usage_can_link_to_a_specific_output(): void
    {
        $output = FaithFlowOutput::factory()->forRun($this->makeRun())->create();
        $usage = FaithFlowUsage::factory()->create([
            'church_id' => $this->church->id,
            'faithflow_run_id' => $output->faithflow_run_id,
            'faithflow_output_id' => $output->id,
            'operation' => 'generate',
        ]);

        $this->assertTrue($usage->output->is($output));
    }

    public function test_usage_has_no_updated_at_column(): void
    {
        $usage = FaithFlowUsage::factory()->forRun($this->makeRun())->create();

        $this->assertArrayNotHasKey('updated_at', $usage->getAttributes());
    }

    public function test_deleting_run_cascades_to_usage(): void
    {
        $run = $this->makeRun();
        $usage = FaithFlowUsage::factory()->forRun($run)->create();

        $run->forceDelete();

        $this->assertDatabaseMissing('faithflow_usage', ['id' => $usage->id]);
    }

    public function test_numeric_fields_cast_to_integers(): void
    {
        $usage = FaithFlowUsage::factory()->forRun($this->makeRun())->create([
            'input_tokens' => '1200',
            'output_tokens' => '340',
            'latency_ms' => '2100',
        ]);

        $this->assertIsInt($usage->fresh()->input_tokens);
        $this->assertIsInt($usage->fresh()->output_tokens);
        $this->assertIsInt($usage->fresh()->latency_ms);
    }
}
