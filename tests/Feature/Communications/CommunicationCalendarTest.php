<?php

namespace Tests\Feature\Communications;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Enums\ContentStatus;
use App\Filament\Pages\CommunicationCalendar;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommunicationCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-02 10:00:00');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_calendar_uses_target_time_and_church_timezone(): void
    {
        $this->actor();
        $campaign = app(CampaignManager::class)->create(['title' => 'Sunday Service']);
        app(CampaignCommunicationManager::class)->add($campaign, [
            'title' => 'Morning announcement',
            'channel' => CommunicationChannel::WHATSAPP,
            'target_at' => '2026-09-03 23:30:00',
        ]);

        Livewire::test(CommunicationCalendar::class)
            ->assertSuccessful()
            ->assertSee('Friday')
            ->assertSee('00:30')
            ->assertSee('Morning announcement')
            ->assertSee('Target dates show communication intent')
            ->assertDontSee('Scheduled post');
    }

    public function test_null_target_is_unplaced_and_created_at_is_not_calendar_truth(): void
    {
        $this->actor();
        $campaign = app(CampaignManager::class)->create(['title' => 'Community Day']);
        app(CampaignCommunicationManager::class)->add($campaign, ['title' => 'Choose a date later', 'channel' => CommunicationChannel::GENERAL]);

        Livewire::test(CommunicationCalendar::class)
            ->assertSee('Nothing is planned for this week')
            ->assertSee('Not yet placed on the Calendar')
            ->assertSee('Choose a date later');
    }

    public function test_past_target_and_needs_changes_use_truthful_language(): void
    {
        $this->actor();
        $campaign = app(CampaignManager::class)->create(['title' => 'Midweek']);
        $content = ContentItem::create(['title' => 'Revise this copy', 'content_type' => 'social_caption', 'body' => 'Copy']);
        $content->forceFill(['status' => ContentStatus::REJECTED])->save();
        $communication = app(CampaignCommunicationManager::class)->add($campaign, [
            'title' => 'Wednesday reminder',
            'channel' => CommunicationChannel::FACEBOOK,
            'target_at' => '2026-09-01 08:00:00',
        ]);
        app(CampaignCommunicationManager::class)->linkContentItem($communication, $content);

        Livewire::test(CommunicationCalendar::class)
            ->assertSee('Target time passed. Outcome not recorded.')
            ->assertSee('Needs Changes')
            ->assertDontSee('Failed to publish');
    }

    public function test_filters_are_bounded_to_channel_and_preparation(): void
    {
        $this->actor();
        $campaign = app(CampaignManager::class)->create(['title' => 'Filtered Campaign']);
        app(CampaignCommunicationManager::class)->add($campaign, ['title' => 'Email item', 'channel' => CommunicationChannel::EMAIL, 'target_at' => '2026-09-03 08:00:00']);
        app(CampaignCommunicationManager::class)->add($campaign, ['title' => 'Social item', 'channel' => CommunicationChannel::INSTAGRAM, 'target_at' => '2026-09-03 09:00:00']);

        Livewire::test(CommunicationCalendar::class)
            ->set('channel', CommunicationChannel::EMAIL->value)
            ->set('preparation', 'not_started')
            ->assertSee('Email item')
            ->assertDontSee('Social item');
    }

    public function test_cancelled_communication_is_excluded_from_active_calendar(): void
    {
        $this->actor();
        $campaign = app(CampaignManager::class)->create(['title' => 'Cancelled Campaign']);
        $communication = app(CampaignCommunicationManager::class)->add($campaign, ['title' => 'Cancelled announcement', 'channel' => CommunicationChannel::SMS, 'target_at' => '2026-09-03 09:00:00']);
        app(CampaignCommunicationManager::class)->cancel($communication);

        Livewire::test(CommunicationCalendar::class)
            ->assertSee('Nothing is planned for this week')
            ->assertDontSee('Cancelled announcement');
    }

    public function test_calendar_query_count_is_bounded_without_n_plus_one(): void
    {
        $this->actor();
        $campaign = app(CampaignManager::class)->create(['title' => 'Bounded Query Campaign']);
        foreach (range(1, 6) as $index) {
            app(CampaignCommunicationManager::class)->add($campaign, ['title' => 'Planned item '.$index, 'channel' => CommunicationChannel::GENERAL, 'target_at' => "2026-09-03 {$index}:00:00"]);
        }
        $queries = [];
        \DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        Livewire::test(CommunicationCalendar::class)->assertSuccessful();

        // Includes the bounded Website handoff eligibility/provenance route
        // discovery added by K-COMMS-001C; still independent of row count.
        // Publication lineage adds two bounded eager-load queries, regardless
        // of how many Calendar rows are rendered.
        $this->assertLessThanOrEqual(24, count($queries));
    }

    private function actor(): Church
    {
        $church = Church::factory()->create(['timezone' => 'Africa/Lagos']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();

        return $church;
    }
}
