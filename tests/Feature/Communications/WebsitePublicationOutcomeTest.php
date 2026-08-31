<?php

namespace Tests\Feature\Communications;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Commercial\Entitlements\EntitlementResolver;
use App\Communications\CommunicationCalendarQuery;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Enums\EntitlementKey;
use App\Enums\WebsiteDraftDestination;
use App\Filament\Pages\CommunicationCalendar;
use App\Models\CampaignCommunication;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsitePublicationProvenance;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use App\Website\Drafts\ApplyApprovedContentToWebsiteDraft;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class WebsitePublicationOutcomeTest extends TestCase
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

    public function test_past_target_with_approved_prepared_draft_never_publishes_and_is_overdue(): void
    {
        $this->actor();
        WebsiteSettings::create([]);
        [$communication, $content] = $this->websiteCommunication('Missed Website target', '2026-09-01 08:00:00');
        app(ApplyApprovedContentToWebsiteDraft::class)->apply($content, WebsiteDraftDestination::HomeWelcome, communication: $communication);

        Livewire::test(CommunicationCalendar::class)
            ->assertSee('Missed Website target')
            ->assertSee('Overdue / not executed')
            ->assertSee('No attributable execution was recorded.');

        $this->assertDatabaseCount('website_publications', 0);
        $this->assertNull(WebsiteSettings::firstOrFail()->current_publication_id);
    }

    public function test_attributable_explicit_publication_completes_calendar_outcome(): void
    {
        $this->actor();
        WebsiteSettings::create([]);
        [$communication, $content] = $this->websiteCommunication('Published Website notice', '2026-09-01 08:00:00');
        $provenance = app(ApplyApprovedContentToWebsiteDraft::class)->apply($content, WebsiteDraftDestination::HomeWelcome, communication: $communication)->provenance;

        $publication = app(WebsitePublisher::class)->publish();

        $this->assertDatabaseHas('website_publication_provenances', [
            'church_id' => $communication->church_id,
            'website_publication_id' => $publication->id,
            'website_content_provenance_id' => $provenance->id,
        ]);
        Livewire::test(CommunicationCalendar::class)
            ->assertSee('Published / completed')
            ->assertSee('View publication')
            ->assertDontSee('Overdue / not executed');
    }

    public function test_unrelated_publication_cannot_complete_a_website_communication(): void
    {
        $this->actor();
        WebsiteSettings::create([]);
        [$communication] = $this->websiteCommunication('Unexecuted communication', '2026-09-01 08:00:00');
        $standalone = $this->approved('Standalone Website copy');
        app(ApplyApprovedContentToWebsiteDraft::class)->apply($standalone, WebsiteDraftDestination::HomeWelcome);
        app(WebsitePublisher::class)->publish();

        $this->assertTrue($communication->fresh()->websiteContentProvenances()->doesntExist());
        Livewire::test(CommunicationCalendar::class)
            ->assertSee('Unexecuted communication')
            ->assertSee('Overdue / not executed')
            ->assertDontSee('Published / completed');
    }

    public function test_diverged_working_copy_preserves_lineage_and_can_be_attributed(): void
    {
        $this->actor();
        WebsiteSettings::create([]);
        [$communication, $content] = $this->websiteCommunication('Adapted Website copy', '2026-09-01 08:00:00');
        $result = app(ApplyApprovedContentToWebsiteDraft::class)->apply($content, WebsiteDraftDestination::HomeWelcome, communication: $communication);
        WebsiteHomeContent::firstOrFail()->update(['welcome_body' => 'Adapted by the Website editor.']);

        $publication = app(WebsitePublisher::class)->publish();

        $this->assertSame('Adapted by the Website editor.', $publication->snapshot['home']['welcome_body']);
        $this->assertDatabaseHas('website_publication_provenances', ['website_content_provenance_id' => $result->provenance->id]);
        $this->assertDatabaseHas('website_content_provenances', ['id' => $result->provenance->id]);
    }

    public function test_later_handoff_supersedes_unpublished_lineage_without_claiming_success(): void
    {
        $this->actor();
        [$firstCommunication, $firstContent] = $this->websiteCommunication('Original Website plan', '2026-09-01 08:00:00');
        app(ApplyApprovedContentToWebsiteDraft::class)->apply($firstContent, WebsiteDraftDestination::HomeWelcome, communication: $firstCommunication);
        [$replacementCommunication, $replacementContent] = $this->websiteCommunication('Replacement Website plan', '2026-09-03 08:00:00');
        app(ApplyApprovedContentToWebsiteDraft::class)->apply($replacementContent, WebsiteDraftDestination::HomeWelcome, replace: true, communication: $replacementCommunication);

        Livewire::test(CommunicationCalendar::class)
            ->assertSee('Original Website plan')
            ->assertSee('Superseded')
            ->assertDontSee('Published / completed');
        $this->assertDatabaseCount('website_publications', 0);
    }

    public function test_cancelled_work_cannot_masquerade_as_success_even_if_it_was_previously_published(): void
    {
        $this->actor();
        WebsiteSettings::create([]);
        [$communication, $content] = $this->websiteCommunication('Cancelled Website plan', '2026-09-01 08:00:00');
        app(ApplyApprovedContentToWebsiteDraft::class)->apply($content, WebsiteDraftDestination::HomeWelcome, communication: $communication);
        app(WebsitePublisher::class)->publish();
        app(CampaignCommunicationManager::class)->cancel($communication);

        $entry = app(CommunicationCalendarQuery::class)->between(
            app(TenantContext::class)->currentChurch(),
            CarbonImmutable::parse('2026-09-01')->startOfDay(),
            CarbonImmutable::parse('2026-09-07')->endOfDay(),
            'Africa/Lagos',
            includeCancelled: true,
        )[0];

        $this->assertSame('cancelled', $entry->outcomeKey);
        $this->assertNull($entry->websitePublicationId);
    }

    public function test_publication_attribution_failure_rolls_back_publication_evidence(): void
    {
        $this->actor();
        WebsiteSettings::create([]);
        [$communication, $content] = $this->websiteCommunication('Atomic Website plan', '2026-09-01 08:00:00');
        app(ApplyApprovedContentToWebsiteDraft::class)->apply($content, WebsiteDraftDestination::HomeWelcome, communication: $communication);
        WebsitePublicationProvenance::creating(fn (): never => throw new RuntimeException('attribution unavailable'));

        try {
            app(WebsitePublisher::class)->publish();
            $this->fail('Publication succeeded without required attribution.');
        } catch (RuntimeException $exception) {
            $this->assertSame('attribution unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('website_publications', 0);
        $this->assertDatabaseCount('website_publication_provenances', 0);
        $this->assertNull(WebsiteSettings::firstOrFail()->current_publication_id);
    }

    public function test_repeated_explicit_publications_do_not_duplicate_attribution_for_a_publication(): void
    {
        $this->actor();
        WebsiteSettings::create([]);
        [$communication, $content] = $this->websiteCommunication('Repeat publication', '2026-09-01 08:00:00');
        app(ApplyApprovedContentToWebsiteDraft::class)->apply($content, WebsiteDraftDestination::HomeWelcome, communication: $communication);

        $first = app(WebsitePublisher::class)->publish();
        $second = app(WebsitePublisher::class)->publish();

        $this->assertSame(1, WebsitePublicationProvenance::where('website_publication_id', $first->id)->count());
        $this->assertSame(1, WebsitePublicationProvenance::where('website_publication_id', $second->id)->count());
    }

    public function test_cross_church_or_browser_supplied_ids_cannot_manufacture_attribution(): void
    {
        [$church] = $this->actor();
        WebsiteSettings::create([]);
        [$communication, $content] = $this->websiteCommunication('Owned lineage', '2026-09-01 08:00:00');
        $provenance = app(ApplyApprovedContentToWebsiteDraft::class)->apply($content, WebsiteDraftDestination::HomeWelcome, communication: $communication)->provenance;
        $publication = app(WebsitePublisher::class)->publish();
        $other = Church::factory()->create();

        $this->expectException(LogicException::class);
        WebsitePublicationProvenance::create([
            'church_id' => $other->id,
            'website_publication_id' => $publication->id,
            'website_content_provenance_id' => $provenance->id,
        ]);
        $this->assertSame($church->id, $publication->church_id);
    }

    /** @return array{Church, User} */
    private function actor(): array
    {
        $church = Church::factory()->create(['timezone' => 'Africa/Lagos']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();
        $resolver = Mockery::mock(EntitlementResolver::class);
        $resolver->shouldReceive('allows')->with(Mockery::type(Church::class), Mockery::type(EntitlementKey::class))->andReturnTrue();
        $this->app->instance(EntitlementResolver::class, $resolver);

        return [$church, $user];
    }

    /** @return array{CampaignCommunication, ContentItem} */
    private function websiteCommunication(string $title, string $targetAt): array
    {
        $campaign = app(CampaignManager::class)->create(['title' => $title.' Campaign']);
        $communication = app(CampaignCommunicationManager::class)->add($campaign, [
            'title' => $title,
            'channel' => CommunicationChannel::WEBSITE,
            'target_at' => $targetAt,
        ]);
        $content = $this->approved($title.' Content');
        app(CampaignCommunicationManager::class)->linkContentItem($communication, $content);

        return [$communication->fresh(), $content];
    }

    private function approved(string $title): ContentItem
    {
        $content = ContentItem::create([
            'title' => $title,
            'content_type' => ContentType::WEBSITE_COPY,
            'body' => 'Approved Website body.',
        ]);
        $content->forceFill([
            'status' => ContentStatus::APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ])->saveQuietly();

        return $content->fresh();
    }
}
