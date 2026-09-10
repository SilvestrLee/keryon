<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Enums\LeadershipCategory;
use App\Enums\PublicationType;
use App\Enums\WebsitePageType;
use App\Models\Church;
use App\Models\ChurchPublication;
use App\Models\User;
use App\Models\WebsiteEvent;
use App\Models\WebsiteGivingContent;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteLeadershipProfile;
use App\Models\WebsiteMessage;
use App\Models\WebsiteMinistry;
use App\Models\WebsitePageSetting;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * K-PROCLAIM-V1-001A — Home's new Events/Messages/Ministries/Publications/
 * Giving teasers. Every assertion here proves the same conditional-module
 * contract stated in the milestone directive (§19): a teaser renders only
 * when its canonical page is BOTH enabled AND has eligible content, and a
 * disabled page never leaks a teaser regardless of content. Anonymous-
 * public evidence uses the same K-WEB-P0-001-hardened pattern already
 * established in `ProclaimRenderingTest::publicGet()`.
 */
class HomeContentTeasersTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Riverside Chapel', 'slug' => 'riverside-chapel']);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        WebsiteSettings::create(['footer_note' => null]);
        WebsiteHomeContent::create(['hero_heading' => 'Welcome to Riverside Chapel']);
    }

    private function enable(WebsitePageType $type): void
    {
        WebsitePageSetting::updateOrCreate(['page_type' => $type->value], ['enabled' => true]);
    }

    private function disable(WebsitePageType $type): void
    {
        WebsitePageSetting::updateOrCreate(['page_type' => $type->value], ['enabled' => false]);
    }

    /** Same anonymity contract as {@see ProclaimRenderingTest::publicGet()}. */
    private function publicGet(string $path = '/')
    {
        $settings = WebsiteSettings::query()->first();
        if ($settings && $settings->current_publication_id === null
            && app(TenantContext::class)->currentChurchId() === $this->church->id) {
            app(WebsitePublisher::class)->publish();
        }

        $authenticated = Auth::user();
        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        $response = $this->get("http://riverside-chapel.keryon.app{$path}");

        if ($authenticated !== null) {
            Auth::login($authenticated);
            app(TenantContext::class)->forgetResolved();
        }

        return $response;
    }

    public function test_home_shows_no_optional_teasers_when_nothing_is_enabled(): void
    {
        $response = $this->publicGet();

        $response->assertOk()
            ->assertDontSee('Upcoming')
            ->assertDontSee('Latest message')
            ->assertDontSee('Find your place')
            ->assertDontSee('From our library');
    }

    public function test_home_shows_upcoming_events_teaser_when_enabled_and_populated(): void
    {
        $this->enable(WebsitePageType::Events);
        WebsiteEvent::create(['title' => 'Harvest Festival', 'starts_at' => now()->addWeek()]);
        WebsiteEvent::create(['title' => 'Old Fellowship Dinner', 'starts_at' => now()->subMonth()]);

        $this->publicGet()->assertOk()->assertSee('Harvest Festival')->assertDontSee('Old Fellowship Dinner');
    }

    public function test_home_hides_events_teaser_when_page_disabled_despite_content(): void
    {
        $this->disable(WebsitePageType::Events);
        WebsiteEvent::create(['title' => 'Hidden Gathering', 'starts_at' => now()->addWeek()]);

        $this->publicGet()->assertOk()->assertDontSee('Hidden Gathering');
    }

    public function test_home_hides_events_teaser_when_enabled_but_no_upcoming_events_exist(): void
    {
        $this->enable(WebsitePageType::Events);
        WebsiteEvent::create(['title' => 'Long Past Gathering', 'starts_at' => now()->subYear()]);

        $this->publicGet()->assertOk()->assertDontSee('Long Past Gathering')->assertDontSee('Upcoming');
    }

    public function test_home_shows_latest_message_preferring_featured_state(): void
    {
        $this->enable(WebsitePageType::Messages);
        WebsiteMessage::create(['title' => 'Ordinary Sunday', 'message_date' => now()->subWeek()]);
        WebsiteMessage::create(['title' => 'The Anchor of Hope', 'message_date' => now()->subDays(2), 'is_featured' => true]);

        $this->publicGet()->assertOk()->assertSee('The Anchor of Hope')->assertDontSee('Ordinary Sunday');
    }

    public function test_home_hides_message_teaser_when_page_disabled_despite_content(): void
    {
        $this->disable(WebsitePageType::Messages);
        WebsiteMessage::create(['title' => 'Hidden Teaching']);

        $this->publicGet()->assertOk()->assertDontSee('Hidden Teaching');
    }

    public function test_home_shows_up_to_three_ministries(): void
    {
        $this->enable(WebsitePageType::Ministries);
        WebsiteMinistry::create(['name' => 'Youth', 'sort_order' => 1]);
        WebsiteMinistry::create(['name' => 'Worship Team', 'sort_order' => 2]);
        WebsiteMinistry::create(['name' => 'Hospitality', 'sort_order' => 3]);
        WebsiteMinistry::create(['name' => 'Prayer Ministry', 'sort_order' => 4]);

        $response = $this->publicGet();

        $response->assertOk()->assertSee('Youth')->assertSee('Worship Team')->assertSee('Hospitality')->assertDontSee('Prayer Ministry');
    }

    public function test_home_hides_ministries_teaser_when_page_disabled_despite_content(): void
    {
        $this->disable(WebsitePageType::Ministries);
        WebsiteMinistry::create(['name' => 'Hidden Ministry']);

        $this->publicGet()->assertOk()->assertDontSee('Hidden Ministry');
    }

    public function test_home_shows_featured_publication(): void
    {
        $this->enable(WebsitePageType::Publications);
        ChurchPublication::create(['title' => 'Untitled Draft', 'publication_type' => PublicationType::Book->value]);
        ChurchPublication::create(['title' => 'Rooted: A 40-Day Devotional', 'publication_type' => PublicationType::Devotional->value, 'is_featured' => true]);

        $this->publicGet()->assertOk()->assertSee('Rooted: A 40-Day Devotional')->assertDontSee('Untitled Draft');
    }

    public function test_home_hides_publications_teaser_when_page_disabled_despite_content(): void
    {
        $this->disable(WebsitePageType::Publications);
        ChurchPublication::create(['title' => 'Hidden Publication']);

        $this->publicGet()->assertOk()->assertDontSee('Hidden Publication');
    }

    public function test_home_shows_giving_teaser_only_when_meaningful_content_exists(): void
    {
        $this->enable(WebsitePageType::Giving);

        $this->publicGet()->assertOk()->assertDontSee('Give to Riverside Chapel')->assertDontSee('Give Now');

        WebsiteGivingContent::create(['headline' => 'Give Generously', 'giving_url' => 'https://giving.example.org']);
        app(WebsitePublisher::class)->publish();

        $this->publicGet()->assertOk()->assertSee('Give Generously');
    }

    public function test_home_hides_giving_teaser_when_page_disabled_despite_content(): void
    {
        $this->disable(WebsitePageType::Giving);
        WebsiteGivingContent::create(['headline' => 'Hidden Giving Pitch']);

        $this->publicGet()->assertOk()->assertDontSee('Hidden Giving Pitch');
    }

    public function test_leadership_never_appears_as_a_home_teaser(): void
    {
        // K-PROCLAIM-V1-001A §18 — deliberately excluded from Home v1A,
        // even though its own dedicated page is enabled and populated.
        $this->enable(WebsitePageType::Leadership);
        WebsiteLeadershipProfile::create(['name' => 'Pastor Amara Okafor', 'category' => LeadershipCategory::PASTOR->value, 'sort_order' => 1]);

        $this->publicGet()->assertOk()->assertDontSee('Pastor Amara Okafor');
    }

    public function test_working_edits_reach_preview_but_public_home_stays_on_the_prior_publication_until_republished(): void
    {
        $this->enable(WebsitePageType::Events);
        WebsiteEvent::create(['title' => 'First Publish Event', 'starts_at' => now()->addWeek()]);
        app(WebsitePublisher::class)->publish();

        $this->publicGet()->assertOk()->assertSee('First Publish Event');

        WebsiteEvent::create(['title' => 'Not Yet Published Event', 'starts_at' => now()->addDays(2)]);

        // K-WEB-P0-001 pattern: after `publicGet()`'s own cross-host
        // request, the URL generator's resolved root can stick to that
        // host — build the preview request against the app's own
        // default test host explicitly, matching the established
        // convention in `WebsitePublishingTest`, rather than `route()`.
        $preview = $this->get('http://localhost/admin/website/preview');
        $preview->assertOk()->assertSee('Not Yet Published Event');

        // Anonymous public Home must still reflect only the prior publication.
        $this->get('http://riverside-chapel.keryon.app/')->assertOk()->assertDontSee('Not Yet Published Event');

        app(WebsitePublisher::class)->publish();

        $this->publicGet()->assertOk()->assertSee('Not Yet Published Event');
    }

    public function test_disabling_a_page_after_publish_removes_its_home_teaser_only_after_republish(): void
    {
        $this->enable(WebsitePageType::Messages);
        WebsiteMessage::create(['title' => 'Standing Message']);
        app(WebsitePublisher::class)->publish();

        $this->publicGet()->assertOk()->assertSee('Standing Message');

        $this->disable(WebsitePageType::Messages);

        // Still live under the prior publication.
        $this->get('http://riverside-chapel.keryon.app/')->assertOk()->assertSee('Standing Message');

        app(WebsitePublisher::class)->publish();

        $this->publicGet()->assertOk()->assertDontSee('Standing Message');
    }

    public function test_home_teasers_never_leak_another_churchs_content(): void
    {
        $this->enable(WebsitePageType::Events);
        app(WebsitePublisher::class)->publish();

        $other = Church::create(['name' => 'Other Church', 'slug' => 'other-church-teasers']);
        app(TenantContext::class)->forgetResolved();
        $this->actingAs(User::factory()->forChurch($other, [ChurchRole::COMMUNICATIONS])->create());
        WebsiteSettings::create(['footer_note' => null]);
        WebsitePageSetting::updateOrCreate(['page_type' => WebsitePageType::Events->value], ['enabled' => true]);
        WebsiteEvent::create(['title' => 'Other Churchs Secret Event', 'starts_at' => now()->addWeek()]);
        app(WebsitePublisher::class)->publish();

        $response = $this->get('http://riverside-chapel.keryon.app/');

        $response->assertOk()->assertDontSee('Other Churchs Secret Event');
    }
}
