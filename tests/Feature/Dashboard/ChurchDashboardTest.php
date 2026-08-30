<?php

namespace Tests\Feature\Dashboard;

use App\Dashboard\ChurchDashboardSnapshotBuilder;
use App\Enums\ChurchOnboardingStatus;
use App\Enums\ChurchOnboardingStep;
use App\Enums\ChurchRole;
use App\Enums\ContentStatus;
use App\Filament\Pages\ChurchDashboard;
use App\Models\ChurchOnboardingState;
use App\Models\ContentItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsDashboardContext;
use Tests\TestCase;

class ChurchDashboardTest extends TestCase
{
    use BuildsDashboardContext;
    use RefreshDatabase;

    public function test_custom_dashboard_replaces_stock_surface_and_renders_action_center(): void
    {
        [, $user] = $this->dashboardActor([ChurchRole::COMMUNICATIONS]);
        ContentItem::query()->create(['title' => 'Review me', 'content_type' => 'announcement', 'body' => 'Body']);
        ContentItem::query()->first()->forceFill(['status' => ContentStatus::REVIEW])->save();

        $this->assertSame('/admin', parse_url(ChurchDashboard::getUrl(), PHP_URL_PATH));
        Livewire::test(ChurchDashboard::class)
            ->assertSuccessful()
            ->assertSee('Needs your attention')
            ->assertSee('awaiting review')
            ->assertDontSee('Filament Info');
    }

    public function test_in_progress_setup_and_precise_identity_gap_are_actions_while_optional_guidance_is_separate(): void
    {
        [$church] = $this->dashboardActor([ChurchRole::COMMUNICATIONS], churchAttributes: ['email' => null]);
        ChurchOnboardingState::query()->create([
            'church_id' => $church->id,
            'status' => ChurchOnboardingStatus::IN_PROGRESS,
            'current_step' => ChurchOnboardingStep::BRAND,
            'started_at' => now(),
        ]);

        $snapshot = app(ChurchDashboardSnapshotBuilder::class)->build();
        $this->assertEqualsCanonicalizing(['onboarding.continue', 'identity.email'], array_column($snapshot->toArray()['actions'], 'key'));
        $this->assertEqualsCanonicalizing(['readiness.brand', 'readiness.service_time'], array_column($snapshot->toArray()['guidance'], 'key'));
    }

    public function test_empty_action_center_is_positive_and_does_not_manufacture_work(): void
    {
        $this->dashboardActor([ChurchRole::ADMINISTRATOR]);
        $snapshot = app(ChurchDashboardSnapshotBuilder::class)->build();
        $this->assertSame([], $snapshot->actions);

        Livewire::test(ChurchDashboard::class)
            ->assertSee('all caught up')
            ->assertDontSee('urgent');
    }

    public function test_dashboard_presents_operational_sections_before_optional_setup(): void
    {
        $this->dashboardActor([ChurchRole::COMMUNICATIONS]);

        Livewire::test(ChurchDashboard::class)
            ->assertSeeInOrder([
                'Needs your attention',
                'What&#039;s happening',
                'Continue your work',
                'Set up when you are ready',
            ], escape: false)
            ->assertSee('Optional');
    }

    public function test_long_church_identity_is_rendered_without_truncating_domain_truth(): void
    {
        $name = 'Keryon Community Church With A Deliberately Long Workspace Identity';
        $this->dashboardActor([ChurchRole::ADMINISTRATOR], churchAttributes: ['name' => $name]);

        Livewire::test(ChurchDashboard::class)
            ->assertSee($name)
            ->assertSeeHtml('data-dashboard-church="'.$name.'"');
    }
}
