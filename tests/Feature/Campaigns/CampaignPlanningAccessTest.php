<?php

namespace Tests\Feature\Campaigns;

use App\Campaigns\CampaignManager;
use App\Enums\ChurchRole;
use App\Filament\Pages\Campaigns as CampaignsPage;
use App\Filament\Pages\CampaignWorkspace;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignPlanningAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_communications_user_can_access_list_and_workspace(): void
    {
        $church = Church::create(['name' => 'Campaign Access Church', 'slug' => 'campaign-access-church']);
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        $campaign = app(CampaignManager::class)->create(['title' => 'Accessible Campaign']);

        Livewire::test(CampaignsPage::class)->assertSuccessful()->assertSee('Accessible Campaign');
        Livewire::test(CampaignWorkspace::class, ['campaign' => $campaign->id])->assertSuccessful()->assertSee('Communication Plan');
    }

    #[DataProvider('deniedResponsibilities')]
    public function test_non_communications_responsibilities_cannot_access(array $roles, bool $primary): void
    {
        $church = Church::create(['name' => 'Denied Campaign Church', 'slug' => 'campaign-denied-'.uniqid()]);
        $this->actingAs(User::factory()->forChurch($church, $roles, $primary)->create());

        $this->assertFalse(CampaignsPage::canAccess());
        $this->assertFalse(CampaignWorkspace::canAccess());
    }

    public static function deniedResponsibilities(): array
    {
        return [
            'care only' => [[ChurchRole::CARE], false],
            'administrator only' => [[ChurchRole::ADMINISTRATOR], false],
            'primary only' => [[], true],
        ];
    }

    public function test_no_context_fails_closed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(CampaignsPage::canAccess());
        $this->assertFalse(CampaignWorkspace::canAccess());
    }

    public function test_multi_church_user_follows_active_context(): void
    {
        $churchA = Church::create(['name' => 'Planning Church A', 'slug' => 'planning-church-a']);
        $churchB = Church::create(['name' => 'Planning Church B', 'slug' => 'planning-church-b']);
        $user = User::factory()->create(['church_id' => $churchB->id]);
        ChurchMembership::factory()->for($user)->for($churchA)->create()->assignRoles([ChurchRole::COMMUNICATIONS]);
        ChurchMembership::factory()->for($user)->for($churchB)->create()->assignRoles([ChurchRole::COMMUNICATIONS]);
        $this->actingAs($user);

        session(['active_church_id' => $churchA->id]);
        app(TenantContext::class)->forgetResolved();
        app(CampaignManager::class)->create(['title' => 'Church A Plan']);

        session(['active_church_id' => $churchB->id]);
        app(TenantContext::class)->forgetResolved();
        app(CampaignManager::class)->create(['title' => 'Church B Plan']);

        Livewire::test(CampaignsPage::class)
            ->assertSee('Church B Plan')
            ->assertDontSee('Church A Plan');
    }
}
