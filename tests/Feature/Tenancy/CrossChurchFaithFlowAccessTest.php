<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossChurchFaithFlowAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Church $churchA;

    protected Church $churchB;

    protected User $userA;

    protected User $userB;

    protected FaithFlowRun $runA;

    protected FaithFlowRun $runB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->churchA = Church::create(['name' => 'Church A', 'slug' => 'faithflow-tenancy-church-a']);
        $this->churchB = Church::create(['name' => 'Church B', 'slug' => 'faithflow-tenancy-church-b']);

        $this->userA = User::factory()->forChurch($this->churchA)->create();
        $this->userB = User::factory()->forChurch($this->churchB)->create();

        $this->runA = FaithFlowRun::factory()->forChurch($this->churchA)->create();
        $this->runB = FaithFlowRun::factory()->forChurch($this->churchB)->create();
    }

    public function test_church_a_user_sees_only_church_a_runs(): void
    {
        $this->actingAs($this->userA);

        $ids = FaithFlowRun::query()->pluck('id')->all();

        $this->assertContains($this->runA->id, $ids);
        $this->assertNotContains($this->runB->id, $ids);
    }

    public function test_church_b_user_sees_only_church_b_runs(): void
    {
        $this->actingAs($this->userB);

        $ids = FaithFlowRun::query()->pluck('id')->all();

        $this->assertContains($this->runB->id, $ids);
        $this->assertNotContains($this->runA->id, $ids);
    }

    public function test_church_a_user_cannot_resolve_church_b_run(): void
    {
        $this->actingAs($this->userA);

        $resolved = FaithFlowRun::query()->find($this->runB->id);

        $this->assertNull($resolved);
    }

    public function test_church_a_user_cannot_update_church_b_run(): void
    {
        $this->actingAs($this->userA);

        $this->assertFalse($this->userA->can('update', $this->runB));
    }

    public function test_run_auto_assigns_church_id_from_authenticated_user(): void
    {
        $this->actingAs($this->userA);

        $run = FaithFlowRun::create([
            'source_text' => 'Sunday sermon notes.',
            'source_char_count' => 21,
        ]);

        $this->assertSame($this->churchA->id, $run->church_id);
    }

    public function test_no_authenticated_church_context_fails_closed(): void
    {
        $this->app['auth']->forgetGuards();

        $ids = FaithFlowRun::query()->pluck('id')->all();

        $this->assertEmpty($ids);
    }

    public function test_outputs_are_tenant_scoped_the_same_way_as_runs(): void
    {
        $outputA = FaithFlowOutput::factory()->forRun($this->runA)->create();
        $outputB = FaithFlowOutput::factory()->forRun($this->runB)->create();

        $this->actingAs($this->userA);
        $idsForA = FaithFlowOutput::query()->pluck('id')->all();
        $this->assertContains($outputA->id, $idsForA);
        $this->assertNotContains($outputB->id, $idsForA);

        $this->actingAs($this->userB);
        $idsForB = FaithFlowOutput::query()->pluck('id')->all();
        $this->assertContains($outputB->id, $idsForB);
        $this->assertNotContains($outputA->id, $idsForB);
    }
}
