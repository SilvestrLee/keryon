<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\FaithFlowUsage;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaithFlowOutputTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Output Test Church', 'slug' => 'output-test-church-'.uniqid()]);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
    }

    protected function makeRun(): FaithFlowRun
    {
        return FaithFlowRun::factory()->forChurch($this->church)->create();
    }

    public function test_output_belongs_to_run(): void
    {
        $run = $this->makeRun();
        $output = FaithFlowOutput::factory()->forRun($run)->create();

        $this->assertTrue($output->run->is($run));
    }

    public function test_output_type_and_status_cast_to_enums(): void
    {
        $output = FaithFlowOutput::factory()->forRun($this->makeRun())->create([
            'output_type' => FaithFlowOutputType::PRAYER_POINTS,
            'status' => FaithFlowOutputStatus::GENERATED,
        ]);

        $this->assertSame(FaithFlowOutputType::PRAYER_POINTS, $output->output_type);
        $this->assertSame(FaithFlowOutputStatus::GENERATED, $output->status);
    }

    public function test_one_live_row_per_output_type_per_run_is_enforced(): void
    {
        $run = $this->makeRun();
        FaithFlowOutput::factory()->forRun($run)->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);

        $this->expectException(QueryException::class);

        FaithFlowOutput::factory()->forRun($run)->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);
    }

    public function test_different_output_types_on_the_same_run_are_allowed(): void
    {
        $run = $this->makeRun();
        $devotional = FaithFlowOutput::factory()->forRun($run)->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);
        $prayerPoints = FaithFlowOutput::factory()->forRun($run)->create(['output_type' => FaithFlowOutputType::PRAYER_POINTS]);

        $this->assertNotSame($devotional->id, $prayerPoints->id);
    }

    public function test_is_edited_reflects_whether_edited_at_is_set(): void
    {
        $untouched = FaithFlowOutput::factory()->forRun($this->makeRun())->generated()->create();
        $edited = FaithFlowOutput::factory()->forRun($this->makeRun())->generated()->create(['edited_at' => now()]);

        $this->assertFalse($untouched->isEdited());
        $this->assertTrue($edited->isEdited());
    }

    public function test_output_belongs_to_approver(): void
    {
        $user = User::factory()->forChurch($this->church)->create();
        $output = FaithFlowOutput::factory()->forRun($this->makeRun())->approved()->create(['approved_by' => $user->id]);

        $this->assertTrue($output->approver->is($user));
    }

    public function test_output_can_link_to_a_content_item_without_modifying_content_items(): void
    {
        $contentItem = ContentItem::create(['title' => 'Handoff', 'content_type' => 'devotional', 'body' => 'Body.']);
        $output = FaithFlowOutput::factory()->forRun($this->makeRun())->approved()->create(['content_item_id' => $contentItem->id]);

        $this->assertTrue($output->contentItem->is($contentItem));
    }

    public function test_content_item_deletion_nulls_the_link_but_preserves_the_output(): void
    {
        $contentItem = ContentItem::create(['title' => 'Handoff', 'content_type' => 'devotional', 'body' => 'Body.']);
        $output = FaithFlowOutput::factory()->forRun($this->makeRun())->approved()->create(['content_item_id' => $contentItem->id]);

        $contentItem->forceDelete();

        $this->assertDatabaseHas('faithflow_outputs', ['id' => $output->id, 'content_item_id' => null]);
    }

    public function test_output_has_many_usage_records(): void
    {
        $output = FaithFlowOutput::factory()->forRun($this->makeRun())->create();
        $usage = FaithFlowUsage::factory()->create([
            'church_id' => $this->church->id,
            'faithflow_run_id' => $output->faithflow_run_id,
            'faithflow_output_id' => $output->id,
        ]);

        $this->assertTrue($output->usage()->first()->is($usage));
    }

    public function test_output_is_soft_deletable(): void
    {
        $output = FaithFlowOutput::factory()->forRun($this->makeRun())->create();
        $id = $output->id;

        $output->delete();

        $this->assertSoftDeleted('faithflow_outputs', ['id' => $id]);
    }

    public function test_deleting_run_cascades_to_outputs(): void
    {
        $run = $this->makeRun();
        $output = FaithFlowOutput::factory()->forRun($run)->create();

        $run->forceDelete();

        $this->assertDatabaseMissing('faithflow_outputs', ['id' => $output->id]);
    }
}
