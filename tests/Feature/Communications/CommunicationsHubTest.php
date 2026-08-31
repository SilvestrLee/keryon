<?php

namespace Tests\Feature\Communications;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Commercial\Entitlements\EntitlementResolver;
use App\Communications\ChurchCommunicationsSnapshotBuilder;
use App\Enums\Capability;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Enums\ContentStatus;
use App\Filament\Pages\CommunicationsHub;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class CommunicationsHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $resolver = Mockery::mock(EntitlementResolver::class);
        $resolver->shouldReceive('allows')->andReturnFalse()->byDefault();
        $this->app->instance(EntitlementResolver::class, $resolver);
    }

    public function test_communications_member_gets_truthful_empty_workspace(): void
    {
        $this->actor([ChurchRole::COMMUNICATIONS]);

        $this->assertTrue(CommunicationsHub::canAccess());
        Livewire::test(CommunicationsHub::class)
            ->assertSuccessful()
            ->assertSee('Your communications workspace is ready')
            ->assertSee('Create Campaign')
            ->assertSee('Content')
            ->assertDontSee('Prayer Requests');
    }

    public function test_care_only_and_administrator_only_members_cannot_enter_hub(): void
    {
        $this->actor([ChurchRole::CARE]);
        $this->assertFalse(CommunicationsHub::canAccess());

        $this->actor([ChurchRole::ADMINISTRATOR]);
        $this->assertFalse(CommunicationsHub::canAccess());
    }

    public function test_relevant_capabilities_are_composed_without_role_name_branching(): void
    {
        $this->assertTrue(ChurchCommunicationsSnapshotBuilder::hasRelevantCapability([Capability::ContentView]));
        $this->assertTrue(ChurchCommunicationsSnapshotBuilder::hasRelevantCapability([Capability::CampaignsView]));
        $this->assertTrue(ChurchCommunicationsSnapshotBuilder::hasRelevantCapability([Capability::DesignsView]));
        $this->assertFalse(ChurchCommunicationsSnapshotBuilder::hasRelevantCapability([Capability::CareView]));
        $this->assertFalse(ChurchCommunicationsSnapshotBuilder::hasRelevantCapability([Capability::ChurchManage]));
    }

    public function test_attention_and_week_counts_come_from_canonical_state(): void
    {
        $church = $this->actor([ChurchRole::COMMUNICATIONS]);
        $review = ContentItem::create(['title' => 'Sunday review', 'content_type' => 'announcement', 'body' => 'Review copy']);
        $review->forceFill(['status' => ContentStatus::REVIEW])->save();
        $campaign = app(CampaignManager::class)->create(['title' => 'Sunday Service']);
        $communication = app(CampaignCommunicationManager::class)->add($campaign, [
            'title' => 'Service announcement',
            'channel' => CommunicationChannel::INSTAGRAM,
            'target_at' => now($church->timezone)->startOfWeek()->addDays(2)->setTime(8, 0)->utc(),
        ]);
        app(CampaignCommunicationManager::class)->linkContentItem($communication, $review);

        Livewire::test(CommunicationsHub::class)
            ->assertSee('1 item is awaiting review')
            ->assertSee('communication planned')
            ->assertSee('Awaiting approval')
            ->assertSee('View full Calendar');
    }

    public function test_hub_does_not_query_care_tables_for_mixed_role_member(): void
    {
        $this->actor([ChurchRole::COMMUNICATIONS, ChurchRole::CARE]);
        $queries = [];
        \DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        Livewire::test(CommunicationsHub::class)->assertSuccessful();

        $this->assertLessThanOrEqual(18, count($queries));
        $this->assertStringNotContainsString('prayer_requests', implode(' ', $queries));
        $this->assertStringNotContainsString('care', strtolower(implode(' ', $queries)));
    }

    /** @param list<ChurchRole> $roles */
    private function actor(array $roles): Church
    {
        $church = Church::factory()->create(['timezone' => 'Africa/Lagos']);
        $user = User::factory()->forChurch($church, $roles)->create();
        $this->actingAs($user);
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();

        return $church;
    }
}
