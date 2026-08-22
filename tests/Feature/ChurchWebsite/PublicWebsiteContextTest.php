<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicWebsiteContextTest extends TestCase
{
    use RefreshDatabase;

    private function site(string $name, string $slug, bool $active = true): Church
    {
        $church = Church::create(['name' => $name, 'slug' => $slug, 'is_active' => $active]);
        if ($active) {
            $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
            WebsiteSettings::create([]);
            WebsiteHomeContent::create(['hero_heading' => "Welcome to {$name}"]);
            app(WebsitePublisher::class)->publish();
            $this->app['auth']->forgetGuards();
        }

        return $church;
    }

    private function publicGet(string $host, string $path = '/')
    {
        return $this->get("http://{$host}{$path}");
    }

    public function test_valid_subdomain_resolves_active_church_without_a_session(): void
    {
        $this->site('Harbour Church', 'harbour');

        $this->publicGet('harbour.keryon.app')
            ->assertOk()
            ->assertSee('Welcome to Harbour Church')
            ->assertSee('<nav', false);

        $this->assertGuest();
    }

    public function test_unknown_and_inactive_churches_fail_closed(): void
    {
        $this->site('Inactive Church', 'inactive', false);

        $this->publicGet('unknown.keryon.app')->assertNotFound();
        $this->publicGet('inactive.keryon.app')->assertNotFound();
    }

    public function test_malformed_or_unsupported_subdomain_does_not_resolve_a_church_website(): void
    {
        $this->site('Safe Church', 'safe-church');

        $this->publicGet('safe.church.keryon.app')->assertNotFound();
        $this->publicGet(str_repeat('a', 64).'.keryon.app')->assertNotFound();
    }

    public function test_sequential_requests_do_not_leak_public_context(): void
    {
        $this->site('North Church', 'north-church');
        $this->site('South Church', 'south-church');

        $this->publicGet('north-church.keryon.app')->assertOk()->assertSee('Welcome to North Church')->assertDontSee('South Church');
        $this->publicGet('south-church.keryon.app')->assertOk()->assertSee('Welcome to South Church')->assertDontSee('North Church');
        $this->publicGet('missing.keryon.app')->assertNotFound();
    }

    public function test_authenticated_session_cannot_change_host_resolved_church(): void
    {
        $publicChurch = $this->site('Public Church', 'public-church');
        $memberChurch = Church::create(['name' => 'Member Church', 'slug' => 'member-church']);
        $user = User::factory()->forChurch($memberChurch, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $this->publicGet('public-church.keryon.app')
            ->assertOk()
            ->assertSee($publicChurch->name)
            ->assertDontSee($memberChurch->name);

        $this->assertSame($memberChurch->id, app(TenantContext::class)->currentChurchId());
    }

    public function test_church_a_host_never_renders_church_b_content(): void
    {
        $this->site('Church A', 'church-a-public');
        $churchB = $this->site('Church B', 'church-b-public');
        DB::table('website_home_contents')->where('church_id', $churchB->id)->update(['hero_heading' => 'Church B confidential heading']);

        $this->publicGet('church-a-public.keryon.app')
            ->assertOk()
            ->assertDontSee('Church B confidential heading');
    }

    public function test_missing_settings_and_unsupported_theme_fail_safely(): void
    {
        Church::create(['name' => 'No Settings Church', 'slug' => 'no-settings']);
        $unsupported = $this->site('Unsupported Theme Church', 'unsupported-theme');
        DB::table('website_publications')->where('church_id', $unsupported->id)->update(['theme' => 'unknown-theme']);

        $this->publicGet('no-settings.keryon.app')->assertNotFound();
        $this->publicGet('unsupported-theme.keryon.app')->assertNotFound();
    }

    public function test_local_development_host_model_is_configurable_without_schema_changes(): void
    {
        $this->assertSame('keryon.app', config('public-website.base_domain'));
        $this->assertArrayHasKey('base_domain', config('public-website'));
    }
}
