<?php

namespace Tests\Feature\Identity;

use App\Enums\ChurchRole;
use App\Filament\Pages\ChurchSetup;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChurchSetupMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_completing_setup_creates_a_primary_membership_with_the_onboarding_default_bundle(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ChurchSetup::class)
            ->set('name', 'New Church')
            ->set('email', '')
            ->set('timezone', 'UTC')
            ->call('save');

        $membership = $user->memberships()->first();

        $this->assertNotNull($membership);
        $this->assertTrue($membership->is_primary);
        $this->assertTrue($membership->hasRole(ChurchRole::ADMINISTRATOR));
        $this->assertTrue($membership->hasRole(ChurchRole::COMMUNICATIONS));
        $this->assertTrue($membership->hasRole(ChurchRole::CARE));
    }

    public function test_completing_setup_mirrors_the_legacy_church_id_compatibility_bridge(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ChurchSetup::class)
            ->set('name', 'New Church')
            ->set('email', '')
            ->set('timezone', 'UTC')
            ->call('save');

        $membership = $user->fresh()->memberships()->first();

        $this->assertSame($membership->church_id, $user->fresh()->church_id);
    }
}
