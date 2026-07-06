<?php

namespace Tests\Feature\CareCenter;

use App\Filament\Pages\CareCenterDashboard;
use App\Models\Church;
use App\Models\PrayerRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CareCenterDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_guest_is_redirected_from_care_center_dashboard(): void
    {
        $response = $this->get('/admin/care-center');

        $response->assertRedirect('/admin/login');
    }

    public function test_authenticated_church_user_can_render_care_center_dashboard(): void
    {
        $church = Church::create(['name' => 'Dashboard QA Church', 'slug' => 'dashboard-qa-church']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);

        Livewire::test(CareCenterDashboard::class)
            ->assertSuccessful();
    }

    public function test_dashboard_shows_own_church_prayer_request_data(): void
    {
        $church = Church::create(['name' => 'Dashboard QA Church', 'slug' => 'dashboard-qa-church']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);

        $newRequest = PrayerRequest::create(['request' => 'Please pray for our own church.', 'status' => 'new']);

        $component = Livewire::test(CareCenterDashboard::class);

        $this->assertSame(1, $component->instance()->getStatusCounts()['new']);
        $this->assertTrue($component->instance()->getNeedsAttention()->contains($newRequest));
    }

    public function test_dashboard_does_not_show_another_churchs_prayer_request_data(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'dashboard-church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'dashboard-church-b']);

        $userA = User::factory()->create(['church_id' => $churchA->id]);
        $userB = User::factory()->create(['church_id' => $churchB->id]);

        $this->actingAs($userB);
        PrayerRequest::create(['request' => 'Please pray for Church B.', 'status' => 'new']);

        $this->actingAs($userA);

        $component = Livewire::test(CareCenterDashboard::class);

        $this->assertSame(0, $component->instance()->getStatusCounts()['new']);
        $this->assertTrue($component->instance()->getNeedsAttention()->isEmpty());
    }
}
