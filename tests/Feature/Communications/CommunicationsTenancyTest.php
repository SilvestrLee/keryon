<?php

namespace Tests\Feature\Communications;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Filament\Pages\CommunicationCalendar;
use App\Filament\Pages\CommunicationsHub;
use App\Models\Church;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommunicationsTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_selected_church_is_the_only_communications_source(): void
    {
        $churchA = Church::factory()->create(['name' => 'Church Alpha']);
        $churchB = Church::factory()->create(['name' => 'Church Beta']);
        $user = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $membershipB = $user->memberships()->create(['church_id' => $churchB->id, 'status' => 'active', 'joined_at' => now()]);
        $membershipB->assignRoles([ChurchRole::COMMUNICATIONS]);

        $this->select($user, $churchA);
        $campaignA = app(CampaignManager::class)->create(['title' => 'Alpha Campaign']);
        app(CampaignCommunicationManager::class)->add($campaignA, ['title' => 'Alpha Message', 'channel' => CommunicationChannel::GENERAL, 'target_at' => now()->startOfWeek()->addDay()]);

        $this->select($user, $churchB);
        $campaignB = app(CampaignManager::class)->create(['title' => 'Beta Campaign']);
        app(CampaignCommunicationManager::class)->add($campaignB, ['title' => 'Beta Message', 'channel' => CommunicationChannel::GENERAL, 'target_at' => now()->startOfWeek()->addDay()]);

        Livewire::test(CommunicationsHub::class)->assertSee('Church Beta')->assertDontSee('Church Alpha');
        Livewire::test(CommunicationCalendar::class)->assertSee('Beta Message')->assertDontSee('Alpha Message');

        $this->select($user, $churchA);
        Livewire::test(CommunicationCalendar::class)->assertSee('Alpha Message')->assertDontSee('Beta Message');
    }

    private function select(User $user, Church $church): void
    {
        $this->actingAs($user);
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();
    }
}
