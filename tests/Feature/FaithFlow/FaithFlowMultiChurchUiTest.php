<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Filament\Pages\FaithFlow;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\FaithFlowRun;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-FAITHFLOW-001F §59 — one User with Church A (Communications) / Church B
 * (Care) must see and use FaithFlow only in the Church A context.
 */
class FaithFlowMultiChurchUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_active_church_determines_visible_runs_and_access(): void
    {
        $churchA = Church::create(['name' => 'Multi UI Church A', 'slug' => 'ff-multi-ui-church-a']);
        $churchB = Church::create(['name' => 'Multi UI Church B', 'slug' => 'ff-multi-ui-church-b']);

        $user = User::factory()->create();
        ChurchMembership::factory()->for($user)->for($churchA)->create()->assignRoles([ChurchRole::COMMUNICATIONS]);
        ChurchMembership::factory()->for($user)->for($churchB)->create()->assignRoles([ChurchRole::CARE]);

        $runA = FaithFlowRun::factory()->forChurch($churchA)->create();

        $this->actingAs($user);

        session(['active_church_id' => $churchA->id]);
        app(TenantContext::class)->forgetResolved();

        $this->assertTrue(FaithFlow::canAccess());
        Livewire::test(FaithFlow::class)->assertSee(\Illuminate\Support\Str::limit($runA->source_text, 140));

        session(['active_church_id' => $churchB->id]);
        app(TenantContext::class)->forgetResolved();

        $this->assertFalse(FaithFlow::canAccess());
    }
}
