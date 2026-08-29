<?php

namespace Tests\Feature\Design;

use App\Design\Actions\CreateDesign;
use App\Design\Actions\RenderDesignOutput;
use App\Design\Actions\RetryDesignOutput;
use App\Design\Rendering\DesignRenderingContextFactory;
use App\Enums\ChurchRole;
use App\Enums\DesignOutputFormat;
use App\Enums\DesignPurpose;
use App\Models\Church;
use App\Models\Design;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class DesignTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_design_queries_and_rendering_context_fail_closed_across_churches(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'design-church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'design-church-b']);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $userB = User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($userA);

        $design = app(CreateDesign::class)->handle(
            'sunday-modern-reference',
            1,
            DesignPurpose::SERVICE,
            ['title' => 'Sunday Worship', 'date' => '2026-08-23', 'time' => '09:30'],
            [DesignOutputFormat::SQUARE],
        );

        $this->actingAs($userB);
        app(TenantContext::class)->forgetResolved();

        $this->assertNull(Design::find($design->id));

        $this->expectException(LogicException::class);
        app(DesignRenderingContextFactory::class)->forDesign($design);
    }

    public function test_independently_invoked_render_and_retry_actions_require_design_management(): void
    {
        $church = Church::factory()->create();
        $communications = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($communications);

        $design = app(CreateDesign::class)->handle(
            'sunday-modern-reference',
            1,
            DesignPurpose::SERVICE,
            ['title' => 'Sunday Worship', 'date' => '2026-08-23', 'time' => '09:30'],
            [DesignOutputFormat::SQUARE],
        );
        $output = $design->outputs->first();
        $output->markFailed('renderer_timeout');

        $careUser = User::factory()->forChurch($church, [ChurchRole::CARE])->create();
        $this->actingAs($careUser);
        app(TenantContext::class)->forgetResolved();

        foreach ([RenderDesignOutput::class, RetryDesignOutput::class] as $action) {
            try {
                app($action)->handle($output->fresh());
                $this->fail("{$action} accepted a user without DesignsManage.");
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }
}
