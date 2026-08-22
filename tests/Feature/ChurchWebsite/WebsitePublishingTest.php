<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\ChurchBrandProfile;
use App\Models\ChurchServiceTime;
use App\Models\ChurchSocialLink;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsitePublication;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebsitePublishingTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $publisher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->church = Church::create([
            'name' => 'Publication Church', 'slug' => 'publication-church',
            'email' => 'first@church.test', 'phone' => '111', 'address' => 'First address',
        ]);
        $this->publisher = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->publisher);
        WebsiteSettings::create([]);
        WebsiteHomeContent::create(['hero_heading' => 'Working draft one', 'hero_subheading' => 'A concise welcome for everyone.']);
        ChurchBrandProfile::create(['primary_color' => '#234E3D']);
        ChurchServiceTime::create(['label' => 'Sunday', 'time' => '10:00 AM']);
        ChurchSocialLink::create(['platform' => 'instagram', 'url' => 'https://instagram.com/first']);
    }

    private function publicGet(string $path = '/')
    {
        return $this->get("http://publication-church.keryon.app{$path}");
    }

    public function test_first_publish_edit_preview_and_republish_are_distinct(): void
    {
        $this->publicGet()->assertNotFound();

        $first = app(WebsitePublisher::class)->publish();
        $this->publicGet()->assertOk()->assertSee('Working draft one');

        WebsiteHomeContent::query()->first()->update(['hero_heading' => 'Working draft two']);
        $this->publicGet()->assertOk()->assertSee('Working draft one')->assertDontSee('Working draft two');
        $this->actingAs($this->publisher);
        $this->get('http://localhost/admin/website/preview')->assertOk()
            ->assertSee('Working draft two')
            ->assertSee('noindex, nofollow');

        $second = app(WebsitePublisher::class)->publish();
        $this->assertNotSame($first->id, $second->id);
        $this->publicGet()->assertOk()->assertSee('Working draft two')->assertDontSee('Working draft one');
    }

    public function test_brand_theme_and_institutional_information_wait_for_republish(): void
    {
        $publication = app(WebsitePublisher::class)->publish();

        ChurchBrandProfile::query()->first()->update(['primary_color' => '#663399']);
        $this->church->update(['email' => 'second@church.test', 'address' => 'Second address']);
        ChurchServiceTime::query()->first()->update(['time' => '11:30 AM']);
        ChurchSocialLink::query()->first()->update(['url' => 'https://instagram.com/second']);
        DB::table('website_settings')->where('church_id', $this->church->id)->update(['theme' => 'unknown-theme']);

        $this->assertSame('#234E3D', $publication->fresh()->snapshot['brand']['primary_color']);
        $this->assertSame('10:00 AM', $publication->fresh()->snapshot['service_times'][0]['time']);
        $this->assertSame('https://instagram.com/first', $publication->fresh()->snapshot['social_links'][0]['url']);

        $this->publicGet()->assertOk()
            ->assertSee('#234E3D')
            ->assertSee('first@church.test')
            ->assertSee('First address')
            ->assertSee('10:00 AM')
            ->assertDontSee('second@church.test');
        $this->get(route('website.preview'))->assertNotFound();

        DB::table('website_settings')->where('church_id', $this->church->id)->update(['theme' => 'proclaim']);
        app(WebsitePublisher::class)->publish();
        $this->publicGet()->assertOk()
            ->assertSee('#663399')
            ->assertSee('second@church.test')
            ->assertSee('Second address')
            ->assertSee('11:30 AM');
    }

    public function test_unpublish_preserves_working_content_and_history(): void
    {
        app(WebsitePublisher::class)->publish();
        app(WebsitePublisher::class)->unpublish();

        $this->publicGet()->assertNotFound();
        $this->assertSame('Working draft one', WebsiteHomeContent::query()->first()->hero_heading);
        $this->assertSame(1, WebsitePublication::query()->count());

        app(WebsitePublisher::class)->publish();
        $this->publicGet()->assertOk()->assertSee('Working draft one');
    }

    public function test_publication_is_atomic_when_pointer_update_fails(): void
    {
        $first = app(WebsitePublisher::class)->publish();
        WebsiteHomeContent::query()->first()->update(['hero_heading' => 'Must roll back']);
        DB::statement("CREATE TRIGGER reject_publication_pointer BEFORE UPDATE OF current_publication_id ON website_settings BEGIN SELECT RAISE(FAIL, 'controlled failure'); END");

        try {
            app(WebsitePublisher::class)->publish();
            $this->fail('The controlled publication failure did not occur.');
        } catch (QueryException) {
            $this->assertSame(1, WebsitePublication::query()->count());
            $this->assertSame($first->id, WebsiteSettings::query()->first()->current_publication_id);
            $this->publicGet()->assertOk()->assertSee('Working draft one')->assertDontSee('Must roll back');
        }
    }

    public function test_publisher_is_trusted_and_cross_church_data_is_not_captured(): void
    {
        $other = Church::create(['name' => 'Private Other', 'slug' => 'private-other']);
        DB::table('website_home_contents')->insert([
            'church_id' => $other->id, 'hero_heading' => 'Other church secret',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $publication = app(WebsitePublisher::class)->publish();

        $this->assertSame($this->publisher->id, $publication->published_by);
        $this->assertSame($this->church->id, $publication->church_id);
        $this->assertStringNotContainsString('Other church secret', json_encode($publication->snapshot));
    }

    public function test_unauthorized_or_contextless_actor_cannot_publish(): void
    {
        $care = User::factory()->forChurch($this->church, [ChurchRole::CARE])->create();
        $this->actingAs($care);
        $this->expectException(AuthorizationException::class);
        app(WebsitePublisher::class)->publish();
    }

    public function test_contextless_actor_cannot_publish(): void
    {
        $this->app['auth']->forgetGuards();

        $this->expectException(AuthorizationException::class);
        app(WebsitePublisher::class)->publish();
    }

    public function test_preview_requires_authentication_membership_and_view_capability(): void
    {
        $this->app['auth']->forgetGuards();
        $this->get('/admin/website/preview')->assertRedirect();

        $care = User::factory()->forChurch($this->church, [ChurchRole::CARE])->create();
        $this->actingAs($care);
        $this->get('/admin/website/preview')->assertForbidden();

        $this->actingAs($this->publisher);
        $this->get('/admin/website/preview')->assertOk()->assertSee('Working draft one');
    }

    public function test_published_seo_sitemap_robots_and_structured_data_are_truthful(): void
    {
        app(WebsitePublisher::class)->publish();

        $this->publicGet()->assertOk()
            ->assertSee('<title>Publication Church</title>', false)
            ->assertSee('name="description"', false)
            ->assertSee('rel="canonical" href="https://publication-church.keryon.app"', false)
            ->assertSee('property="og:title" content="Publication Church"', false)
            ->assertSee('name="robots" content="index, follow"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('https://schema.org', false);
        $this->publicGet('/about')->assertSee('<title>About | Publication Church</title>', false);
        $this->publicGet('/sitemap.xml')->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8')
            ->assertSee('https://publication-church.keryon.app/contact');
        $this->publicGet('/robots.txt')->assertOk()
            ->assertSee('Allow: /')
            ->assertSee('/sitemap.xml');
    }

    public function test_published_unpublished_and_unknown_hosts_do_not_leak_sequentially(): void
    {
        app(WebsitePublisher::class)->publish();
        $private = Church::create(['name' => 'Private Church', 'slug' => 'private-church']);
        $privateUser = User::factory()->forChurch($private, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($privateUser);
        WebsiteSettings::create([]);
        WebsiteHomeContent::create(['hero_heading' => 'Private working content']);

        $this->publicGet()->assertOk()->assertSee('Working draft one')->assertDontSee('Private working content');
        $this->get('http://private-church.keryon.app')->assertNotFound();
        $this->get('http://unknown-publication.keryon.app')->assertNotFound();
    }
}
