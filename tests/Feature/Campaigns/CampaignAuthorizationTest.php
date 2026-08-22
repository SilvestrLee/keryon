<?php

namespace Tests\Feature\Campaigns;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Models\Campaign;
use App\Models\CampaignCommunication;
use App\Models\Church;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roleCases')]
    public function test_campaign_and_communication_authorization(array $roles, bool $primary, bool $allowed): void
    {
        $church = Church::create(['name' => 'Authorization Church', 'slug' => 'campaign-auth-'.uniqid()]);
        $communicationsUser = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($communicationsUser);
        $campaign = app(CampaignManager::class)->create(['title' => 'Authorization Campaign']);
        $communication = app(CampaignCommunicationManager::class)->add($campaign, [
            'title' => 'Plan entry',
            'channel' => CommunicationChannel::GENERAL,
        ]);

        $user = User::factory()->forChurch($church, $roles, $primary)->create();
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();

        $this->assertSame($allowed, Gate::allows('viewAny', Campaign::class));
        $this->assertSame($allowed, Gate::allows('view', $campaign));
        $this->assertSame($allowed, Gate::allows('create', Campaign::class));
        $this->assertSame($allowed, Gate::allows('update', $campaign));
        $this->assertSame($allowed, Gate::allows('viewAny', CampaignCommunication::class));
        $this->assertSame($allowed, Gate::allows('view', $communication));
        $this->assertSame($allowed, Gate::allows('update', $communication));
    }

    public static function roleCases(): array
    {
        return [
            'communications' => [[ChurchRole::COMMUNICATIONS], false, true],
            'care only' => [[ChurchRole::CARE], false, false],
            'administrator only' => [[ChurchRole::ADMINISTRATOR], false, false],
            'primary only' => [[], true, false],
            'communications plus care' => [[ChurchRole::COMMUNICATIONS, ChurchRole::CARE], false, true],
        ];
    }

    public function test_no_active_membership_cannot_create_campaign(): void
    {
        $this->actingAs(User::factory()->create());
        app(TenantContext::class)->forgetResolved();

        $this->expectException(AuthorizationException::class);
        app(CampaignManager::class)->create(['title' => 'Denied']);
    }
}
