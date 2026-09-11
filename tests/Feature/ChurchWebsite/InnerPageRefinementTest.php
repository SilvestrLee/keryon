<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Enums\LeadershipCategory;
use App\Models\Church;
use App\Models\User;
use App\Models\WebsiteAboutContent;
use App\Models\WebsiteLeadershipProfile;
use App\Models\WebsiteMinistry;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * K-PROCLAIM-V1-001B — About's confirmed missing empty state, Contact's
 * tel:/mailto: links (already present pre-milestone, now explicitly
 * regression-tested for the first time), and Leadership/Ministries
 * ordering/isolation. Anonymity contract matches
 * {@see ProclaimRenderingTest::publicGet()}.
 */
class InnerPageRefinementTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create([
            'name' => 'Riverside Chapel',
            'slug' => 'riverside-chapel-inner',
            'email' => 'hello@riverside.test',
            'phone' => '+234 701 789 1841',
            'address' => '12 River Road, Lagos',
        ]);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        WebsiteSettings::create(['footer_note' => null]);
    }

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

        $response = $this->get("http://riverside-chapel-inner.keryon.app{$path}");

        if ($authenticated !== null) {
            Auth::login($authenticated);
            app(TenantContext::class)->forgetResolved();
        }

        return $response;
    }

    public function test_about_shows_a_visitor_appropriate_empty_state_when_entirely_unconfigured(): void
    {
        $response = $this->publicGet('/about');

        $response->assertOk()
            ->assertSee('Our story is coming soon.')
            ->assertSee('Please check back to learn more about Riverside Chapel.')
            ->assertDontSee('No record found')
            ->assertDontSee('No data')
            ->assertDontSee('Nothing configured');
    }

    public function test_about_empty_state_disappears_once_any_field_is_populated(): void
    {
        WebsiteAboutContent::create(['church_story' => 'We began as a small home Bible study.']);

        $this->publicGet('/about')->assertOk()
            ->assertSee('We began as a small home Bible study')
            ->assertDontSee('Our story is coming soon.');
    }

    public function test_about_empty_state_does_not_appear_when_only_leadership_introduction_is_set(): void
    {
        WebsiteAboutContent::create(['leadership_introduction' => 'Our elders shepherd this church in prayer.']);

        $this->publicGet('/about')->assertOk()
            ->assertSee('Our elders shepherd this church in prayer')
            ->assertDontSee('Our story is coming soon.');
    }

    public function test_contact_phone_renders_a_safe_tel_link(): void
    {
        $response = $this->publicGet('/contact');

        $response->assertOk()->assertSee('href="tel:+2347017891841"', false);
    }

    public function test_contact_email_renders_an_escaped_mailto_link(): void
    {
        $response = $this->publicGet('/contact');

        $response->assertOk()->assertSee('href="mailto:hello@riverside.test"', false);
    }

    public function test_contact_renders_gracefully_with_only_phone_present(): void
    {
        $church = Church::create(['name' => 'Phone Only Chapel', 'slug' => 'phone-only-chapel', 'phone' => '0801 234 5678']);
        app(TenantContext::class)->forgetResolved();
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        WebsiteSettings::create(['footer_note' => null]);
        app(WebsitePublisher::class)->publish();

        $response = $this->get('http://phone-only-chapel.keryon.app/contact');

        $response->assertOk()
            ->assertSee('0801 234 5678')
            ->assertSee('href="tel:08012345678"', false)
            ->assertDontSee('mailto:');
    }

    public function test_contact_renders_gracefully_with_no_contact_fields_at_all(): void
    {
        $church = Church::create(['name' => 'Bare Chapel', 'slug' => 'bare-chapel']);
        app(TenantContext::class)->forgetResolved();
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        WebsiteSettings::create(['footer_note' => null]);
        app(WebsitePublisher::class)->publish();

        $response = $this->get('http://bare-chapel.keryon.app/contact');

        $response->assertOk()->assertSee('Contact Bare Chapel')->assertDontSee('tel:')->assertDontSee('mailto:');
    }

    public function test_leadership_groups_and_orders_multiple_profiles_deterministically(): void
    {
        WebsiteLeadershipProfile::create(['name' => 'Second Elder', 'category' => LeadershipCategory::ELDER->value, 'sort_order' => 2]);
        WebsiteLeadershipProfile::create(['name' => 'First Elder', 'category' => LeadershipCategory::ELDER->value, 'sort_order' => 1]);
        WebsiteLeadershipProfile::create(['name' => 'The Pastor', 'category' => LeadershipCategory::PASTOR->value, 'sort_order' => 1]);

        $response = $this->publicGet('/leadership');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertTrue(
            strpos($body, 'First Elder') < strpos($body, 'Second Elder'),
            'Elders must render in sort_order, not creation order.'
        );
    }

    public function test_ministries_renders_long_names_and_descriptions_without_error(): void
    {
        WebsiteMinistry::create([
            'name' => 'Community Outreach and Benevolence Partnership Ministry',
            'description' => str_repeat('Partnering with local shelters and schools to meet practical needs. ', 4),
            'sort_order' => 1,
        ]);

        $this->publicGet('/ministries')->assertOk()->assertSee('Community Outreach and Benevolence Partnership Ministry');
    }

    public function test_working_edits_to_about_and_leadership_stay_off_the_public_page_until_republished(): void
    {
        WebsiteAboutContent::create(['church_story' => 'Original story.']);
        app(WebsitePublisher::class)->publish();

        $this->publicGet('/about')->assertOk()->assertSee('Original story.');

        WebsiteAboutContent::first()->update(['church_story' => 'Revised, not yet published story.']);
        WebsiteLeadershipProfile::create(['name' => 'Newly Added Leader', 'category' => LeadershipCategory::ELDER->value]);

        $this->get('http://riverside-chapel-inner.keryon.app/about')
            ->assertOk()->assertSee('Original story.')->assertDontSee('Revised, not yet published');
        $this->get('http://riverside-chapel-inner.keryon.app/leadership')
            ->assertOk()->assertDontSee('Newly Added Leader');

        app(WebsitePublisher::class)->publish();

        $this->publicGet('/about')->assertOk()->assertSee('Revised, not yet published story.');
        $this->publicGet('/leadership')->assertOk()->assertSee('Newly Added Leader');
    }

    public function test_leadership_and_ministries_never_leak_another_churchs_content(): void
    {
        app(WebsitePublisher::class)->publish();

        $other = Church::create(['name' => 'Other Inner Church', 'slug' => 'other-inner-church']);
        app(TenantContext::class)->forgetResolved();
        $this->actingAs(User::factory()->forChurch($other, [ChurchRole::COMMUNICATIONS])->create());
        WebsiteSettings::create(['footer_note' => null]);
        WebsiteLeadershipProfile::create(['name' => 'Other Churchs Secret Elder', 'category' => LeadershipCategory::ELDER->value]);
        WebsiteMinistry::create(['name' => 'Other Churchs Secret Ministry']);
        app(WebsitePublisher::class)->publish();

        $this->get('http://riverside-chapel-inner.keryon.app/leadership')->assertOk()->assertDontSee('Other Churchs Secret Elder');
        $this->get('http://riverside-chapel-inner.keryon.app/ministries')->assertOk()->assertDontSee('Other Churchs Secret Ministry');
    }
}
