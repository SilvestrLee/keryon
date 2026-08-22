<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\BrandFontChoice;
use App\Enums\ChurchRole;
use App\Enums\LeadershipCategory;
use App\Models\Church;
use App\Models\ChurchBrandProfile;
use App\Models\ChurchServiceTime;
use App\Models\ChurchSocialLink;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\WebsiteAboutContent;
use App\Models\WebsiteContactContent;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteLeadershipProfile;
use App\Models\WebsiteMinistry;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProclaimRenderingTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->church = Church::create([
            'name' => 'The Fellowship of Grace and Hope Community Church',
            'slug' => 'grace-and-hope',
            'email' => 'hello@grace.test',
            'phone' => '+234 800 555 0199',
            'address' => '18 Community Road, Lagos',
        ]);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
        WebsiteSettings::create(['footer_note' => 'A church family in Lagos.']);
    }

    private function publicGet(string $path = '/')
    {
        $settings = WebsiteSettings::query()->first();
        if ($settings && $settings->current_publication_id === null
            && app(TenantContext::class)->currentChurchId() === $this->church->id) {
            app(WebsitePublisher::class)->publish();
        }

        return $this->get("http://grace-and-hope.keryon.app{$path}");
    }

    private function image(string $filename, string $alt): MediaAsset
    {
        $uuid = (string) Str::uuid();
        $path = "tenants/{$this->church->id}/media/{$uuid}/original.png";
        Storage::disk('public')->put($path, 'image bytes');
        $asset = new MediaAsset([
            'disk' => 'public', 'path' => $path, 'original_filename' => $filename,
            'mime_type' => 'image/png', 'size' => 11, 'width' => 1200, 'height' => 800,
            'alt_text' => $alt,
        ]);
        $asset->uuid = $uuid;
        $asset->save();

        return $asset;
    }

    public function test_home_renders_structured_content_brand_media_and_durable_semantics(): void
    {
        $hero = $this->image('sunday.png', 'The congregation worshipping together');
        ChurchBrandProfile::create([
            'primary_color' => '#234E3D',
            'heading_font' => BrandFontChoice::PLAYFAIR_DISPLAY->value,
            'body_font' => BrandFontChoice::INTER->value,
        ]);
        WebsiteHomeContent::create([
            'hero_heading' => 'A home for faith, hope, and generous community',
            'hero_subheading' => 'Join us as we worship, learn, and serve our city together.',
            'hero_cta_label' => 'Plan your visit',
            'hero_cta_url' => '/contact',
            'hero_image_id' => $hero->id,
            'welcome_heading' => 'There is a place for you here',
            'welcome_body' => 'Come as you are and grow with a caring church family.',
            'scripture_reference' => 'Romans 15:7',
            'scripture_text' => 'Welcome one another as Christ has welcomed you.',
        ]);
        ChurchServiceTime::create(['label' => 'Sunday Worship', 'time' => '10:00 AM', 'sort_order' => 1]);

        $response = $this->publicGet();

        $response->assertOk()
            ->assertSee('<title>The Fellowship of Grace and Hope Community Church</title>', false)
            ->assertSee('<main id="main-content">', false)
            ->assertSee('aria-label="Primary navigation"', false)
            ->assertSee('<h1>A home for faith, hope, and generous community</h1>', false)
            ->assertSee('alt="The congregation worshipping together"', false)
            ->assertSee(Storage::disk('public')->url($hero->path))
            ->assertSee('--church-accent: #234E3D', false)
            ->assertSee('Romans 15:7');
    }

    public function test_about_leadership_ministries_and_contact_render_canonical_content(): void
    {
        WebsiteAboutContent::create([
            'church_story' => 'We began by gathering in a family home.',
            'vision' => 'A flourishing church for the whole city.',
            'mission' => 'Love God, form disciples, and serve our neighbours.',
            'leadership_introduction' => 'Our leaders serve with prayer and accountability.',
        ]);
        WebsiteLeadershipProfile::create([
            'name' => 'Pastor Amara Okafor', 'category' => LeadershipCategory::PASTOR->value,
            'role_title' => 'Lead Pastor', 'bio' => 'Amara teaches and cares for the church.', 'sort_order' => 1,
        ]);
        WebsiteMinistry::create(['name' => 'Young Adults', 'description' => 'Friendship, formation, and service for young adults.', 'sort_order' => 1]);
        WebsiteContactContent::create(['office_hours' => 'Monday to Thursday, 9:00 AM to 4:00 PM', 'map_embed_url' => 'https://maps.example.test/grace']);
        ChurchServiceTime::create(['label' => 'Sunday Worship', 'time' => '10:00 AM']);
        ChurchSocialLink::create(['platform' => 'instagram', 'url' => 'https://instagram.com/gracechurch']);

        $this->publicGet('/about')->assertOk()->assertSee('We began by gathering')->assertSee('A flourishing church')->assertSee('Love God');
        $this->publicGet('/leadership')->assertOk()->assertSee('Pastor Amara Okafor')->assertSee('Lead Pastor')->assertSee('<h1>People who serve with care.</h1>', false);
        $this->publicGet('/ministries')->assertOk()->assertSee('Young Adults')->assertSee('Friendship, formation')->assertSee('<h1>Find a place to grow and belong.</h1>', false);
        $this->publicGet('/contact')->assertOk()->assertSee('hello@grace.test')->assertSee('+234 800 555 0199')->assertSee('18 Community Road')->assertSee('Sunday Worship')->assertSee('Instagram');
    }

    public function test_empty_collections_and_missing_optional_media_render_gracefully(): void
    {
        WebsiteHomeContent::create(['hero_heading' => str_repeat('A welcoming church with a long and resilient heading ', 3)]);

        $this->publicGet()->assertOk()->assertSee('A welcoming church');
        $this->publicGet('/leadership')->assertOk()->assertSee('Leadership information is coming soon.');
        $this->publicGet('/ministries')->assertOk()->assertSee('Ministry information is coming soon.');
        $this->publicGet('/contact')->assertOk()->assertSee('We would love to welcome you.');
    }

    public function test_cross_church_media_and_deleted_or_unsafe_media_never_render(): void
    {
        app(WebsitePublisher::class)->publish();
        $other = Church::create(['name' => 'Other Church', 'slug' => 'other-media']);
        $otherUser = User::factory()->forChurch($other, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($otherUser);
        $foreign = $this->imageForChurch($other, 'foreign-secret');

        DB::table('website_home_contents')->insert([
            'church_id' => $this->church->id,
            'hero_heading' => 'Safe heading',
            'hero_image_id' => $foreign->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->publicGet()->assertOk()->assertDontSee('foreign-secret')->assertDontSee($foreign->path);
    }

    public function test_public_delivery_rejects_svg_even_if_an_unsafe_row_exists(): void
    {
        $uuid = (string) Str::uuid();
        $path = "tenants/{$this->church->id}/media/{$uuid}/original.svg";
        Storage::disk('public')->put($path, '<svg><script>alert(1)</script></svg>');
        $assetId = DB::table('media_assets')->insertGetId([
            'church_id' => $this->church->id,
            'uuid' => $uuid,
            'disk' => 'public',
            'path' => $path,
            'original_filename' => 'unsafe.svg',
            'mime_type' => 'image/svg+xml',
            'size' => 38,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('website_home_contents')->insert([
            'church_id' => $this->church->id,
            'hero_heading' => 'Safe media delivery',
            'hero_image_id' => $assetId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->publicGet()->assertOk()
            ->assertDontSee('original.svg')
            ->assertDontSee('unsafe.svg');
    }

    private function imageForChurch(Church $church, string $marker): MediaAsset
    {
        $uuid = (string) Str::uuid();
        $path = "tenants/{$church->id}/media/{$uuid}/original.png";
        Storage::disk('public')->put($path, $marker);
        $asset = new MediaAsset([
            'disk' => 'public', 'path' => $path, 'original_filename' => 'foreign.png',
            'mime_type' => 'image/png', 'size' => strlen($marker), 'alt_text' => $marker,
        ]);
        $asset->uuid = $uuid;
        $asset->save();

        return $asset;
    }

    public function test_theme_rendering_does_not_mutate_content(): void
    {
        $home = WebsiteHomeContent::create(['hero_heading' => 'Immutable canonical heading']);
        $before = $home->fresh()->getAttributes();

        $this->publicGet()->assertOk()->assertSee('Immutable canonical heading');

        $this->assertSame($before, $home->fresh()->getAttributes());
    }

    public function test_mobile_navigation_has_accessible_state_and_touch_control(): void
    {
        WebsiteHomeContent::create(['hero_heading' => 'Accessible home']);

        $this->publicGet()->assertOk()
            ->assertSee('aria-controls="mobile-navigation"', false)
            ->assertSee(':aria-expanded="menuOpen.toString()"', false)
            ->assertSee('@keydown.escape.window="menuOpen = false"', false)
            ->assertSee('Skip to content');
    }

    public function test_untrusted_public_links_are_filtered_at_render_time(): void
    {
        DB::table('website_home_contents')->insert([
            'church_id' => $this->church->id,
            'hero_heading' => 'Safe public links',
            'hero_cta_label' => 'Unsafe action',
            'hero_cta_url' => 'javascript:alert(1)',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('church_social_links')->insert([
            'church_id' => $this->church->id,
            'platform' => 'instagram',
            'url' => 'javascript:alert(2)',
            'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->publicGet()->assertOk()
            ->assertDontSee('javascript:alert', false)
            ->assertDontSee('Unsafe action');
    }
}
