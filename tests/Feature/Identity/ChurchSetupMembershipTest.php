<?php

namespace Tests\Feature\Identity;

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

    public function test_legacy_setup_cannot_create_a_church_or_membership(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ChurchSetup::class)
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, $user->memberships()->count());
        $this->assertNull($user->fresh()->church_id);
    }
}
