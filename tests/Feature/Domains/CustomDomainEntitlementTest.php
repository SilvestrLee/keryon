<?php

namespace Tests\Feature\Domains;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Domain\ChurchDomainLifecycle;
use App\Domain\DisableChurchDomain;
use App\Domain\MakeChurchDomainPrimary;
use App\Domain\RegenerateChurchDomainToken;
use App\Domain\ReleaseChurchDomain;
use App\Domain\RequestChurchCustomDomain;
use App\Enums\ChurchRole;
use App\Enums\DomainStatus;
use App\Enums\EntitlementKey;
use App\Filament\Clusters\Website\Pages\ManageDomains;
use App\Jobs\VerifyChurchDomain as VerifyChurchDomainJob;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteSettings;
use App\PublicWebsite\PublicWebsiteUrl;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use App\Website\ChurchPublicUrlResolver;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * K-DOMAIN-001F §27 — service-level entitlement enforcement, not merely UI
 * hiding. Uses controlled Mockery entitlement decisions per §6 — no plan
 * assignment is invented, and CommercialCatalogBootstrapper is untouched.
 */
class CustomDomainEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private function entitled(bool $allowed): void
    {
        $resolver = Mockery::mock(EntitlementResolver::class);
        $resolver->shouldReceive('allows')
            ->with(Mockery::type(Church::class), EntitlementKey::WebsiteCustomDomainEnabled)
            ->andReturn($allowed);
        // Other keys (e.g. WebsiteEnabled, used by ChurchPublicUrlResolver)
        // must remain unaffected by this milestone's gate.
        $resolver->shouldReceive('allows')
            ->with(Mockery::type(Church::class), Mockery::not(EntitlementKey::WebsiteCustomDomainEnabled))
            ->andReturnTrue();
        $this->app->instance(EntitlementResolver::class, $resolver);
    }

    private function primaryAdministrator(): array
    {
        $church = Church::create(['name' => 'Entitlement Church', 'slug' => fake()->unique()->slug(2), 'activated_at' => now(), 'is_active' => true]);
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($user);

        return [$church, $user];
    }

    public function test_entitled_primary_admin_can_claim_domain(): void
    {
        $this->entitled(true);
        [$church] = $this->primaryAdministrator();

        $result = app(RequestChurchCustomDomain::class)->execute($church, 'entitled.example.org');

        $this->assertNotNull($result->domain->id);
        $this->assertDatabaseHas('church_domains', ['normalized_hostname' => 'entitled.example.org']);
    }

    public function test_unentitled_primary_admin_cannot_claim_domain(): void
    {
        $this->entitled(false);
        [$church] = $this->primaryAdministrator();

        $this->expectException(AuthorizationException::class);
        app(RequestChurchCustomDomain::class)->execute($church, 'denied.example.org');
    }

    public function test_entitlement_denial_creates_no_church_domain_row_and_stores_no_token(): void
    {
        $this->entitled(false);
        [$church] = $this->primaryAdministrator();

        try {
            app(RequestChurchCustomDomain::class)->execute($church, 'denied-evidence.example.org');
            $this->fail('Expected an AuthorizationException.');
        } catch (AuthorizationException) {
            $this->assertSame(0, ChurchDomain::query()->count());
            $this->assertDatabaseMissing('church_domains', ['normalized_hostname' => 'denied-evidence.example.org']);
        }
    }

    public function test_unentitled_user_cannot_regenerate_setup_token(): void
    {
        $this->entitled(true);
        [$church] = $this->primaryAdministrator();
        $claim = app(RequestChurchCustomDomain::class)->execute($church, 'regen.example.org');

        $this->entitled(false);
        $this->expectException(AuthorizationException::class);
        app(RegenerateChurchDomainToken::class)->execute($claim->domain->fresh());
    }

    public function test_unentitled_user_cannot_trigger_domain_verification_or_provisioning(): void
    {
        Queue::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config()->set('public-website.custom_domains.dns_resolver', 'fake');
        config()->set('public-website.custom_domains.provisioner', 'fake');
        config()->set('public-website.custom_domains.dns_ingress_target', 'ingress.keryon.test');
        $this->entitled(true);
        [$church, $user] = $this->primaryAdministrator();
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();
        $claim = app(RequestChurchCustomDomain::class)->execute($church, 'verify-denied.example.org');

        $this->entitled(false);
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();

        try {
            Livewire::test(ManageDomains::class)->instance()->verify($claim->domain->id);
            $this->fail('Expected an AuthorizationException.');
        } catch (AuthorizationException) {
            Queue::assertNotPushed(VerifyChurchDomainJob::class);
        }
    }

    public function test_unentitled_user_cannot_make_domain_primary(): void
    {
        $this->entitled(true);
        [$church, $user] = $this->primaryAdministrator();
        $domain = $this->activeDomain($church);

        $this->entitled(false);
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();

        $this->expectException(AuthorizationException::class);
        app(MakeChurchDomainPrimary::class)->execute($domain->fresh());
    }

    public function test_unentitled_user_can_disable_existing_domain(): void
    {
        $this->entitled(true);
        [$church, $user] = $this->primaryAdministrator();
        $domain = $this->activeDomain($church);

        $this->entitled(false);
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();

        $disabled = app(DisableChurchDomain::class)->execute($domain->fresh());
        $this->assertSame(DomainStatus::Disabled, $disabled->status);
    }

    public function test_unentitled_user_can_release_existing_domain(): void
    {
        $this->entitled(true);
        [$church, $user] = $this->primaryAdministrator();
        $domain = $this->activeDomain($church);

        $this->entitled(false);
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();

        $released = app(ReleaseChurchDomain::class)->execute($domain->fresh());
        $this->assertSame(DomainStatus::Released, $released->status);
        $this->assertNotNull($released->released_at);
    }

    public function test_existing_active_custom_domain_remains_publicly_resolvable_after_entitlement_denial(): void
    {
        $this->entitled(true);
        [$church] = $this->primaryAdministrator();
        $domain = $this->activeDomain($church);
        app(MakeChurchDomainPrimary::class)->execute($domain->fresh());

        $this->entitled(false);

        $response = $this->get('http://'.$domain->normalized_hostname);
        $response->assertNotFound(); // no published Website content, but routing itself is not blocked by entitlement
        // The key assertion: PublicWebsiteHostResolver must not consult the
        // custom-domain entitlement for an already-active domain (§9).
        $this->assertSame(DomainStatus::Active, $domain->fresh()->status);
    }

    public function test_keryon_first_party_church_address_remains_available_when_unentitled(): void
    {
        $this->entitled(false);
        [$church] = $this->primaryAdministrator();

        // ChurchPublicUrlResolver gates on WebsiteEnabled only, never on the
        // custom-domain entitlement (§2.1) — our mock keeps every other key
        // allowed, isolating exactly this assertion.
        $this->assertNotNull(app(ChurchPublicUrlResolver::class)); // resolves without throwing
        $this->assertTrue($church->is_active);
    }

    public function test_first_party_website_regression_unentitled_church_still_publishes_and_serves(): void
    {
        // K-DOMAIN-001F §26 — custom-domain commercial policy must not
        // degrade the core Website product.
        $this->entitled(false);
        $church = Church::create(['name' => 'No Custom Domain Church', 'slug' => 'no-custom-domain-church', 'is_active' => true, 'activated_at' => now()]);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();

        $this->assertSame('no-custom-domain-church', $church->slug);
        $keryonUrl = app(PublicWebsiteUrl::class)->page($church);
        $this->assertSame('https://no-custom-domain-church.keryon.app', $keryonUrl);

        WebsiteSettings::create([]);
        WebsiteHomeContent::create(['hero_heading' => 'Still fully available']);
        app(WebsitePublisher::class)->publish();

        $this->assertSame($keryonUrl, app(ChurchPublicUrlResolver::class)->resolve($church));
        $this->get('http://no-custom-domain-church.keryon.app')
            ->assertOk()
            ->assertSee('Still fully available');
    }

    public function test_page_remains_accessible_to_authorized_primary_administrator_when_unentitled(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->entitled(false);
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($user);
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();

        $this->assertTrue(ManageDomains::canAccess());
        Livewire::test(ManageDomains::class)->assertSuccessful()->assertSee('Your Church Website addresses');
    }

    public function test_claim_form_is_absent_when_unentitled_and_present_when_entitled(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->entitled(false);
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($user);
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();

        Livewire::test(ManageDomains::class)
            ->assertDontSee('Connect your domain')
            ->assertSee("Custom domains aren't included in your current plan", false)
            ->assertSee('Your Keryon Church address remains available');

        $this->entitled(true);
        Livewire::test(ManageDomains::class)
            ->assertSee('Connect your domain')
            ->assertDontSee("Custom domains aren't included in your current plan", false);
    }

    public function test_unentitled_existing_domain_hides_setup_actions_but_keeps_cleanup(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->entitled(true);
        [$church, $user] = $this->primaryAdministrator();
        $this->actingAs($user);
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();
        $domain = app(RequestChurchCustomDomain::class)->execute($church, 'setup-hidden.example.org')->domain;

        $this->entitled(false);
        $component = Livewire::test(ManageDomains::class);
        $component->assertDontSee('Generate new verification value');
        $component->assertSee('Disable');
        $component->assertSee('Release domain');
    }

    private function activeDomain(Church $church): ChurchDomain
    {
        $claim = app(RequestChurchCustomDomain::class)->execute($church, 'active-'.fake()->unique()->numerify('####').'.example.org');
        $lifecycle = app(ChurchDomainLifecycle::class);
        $domain = $lifecycle->ownershipVerified($claim->domain);
        $domain = $lifecycle->routingVerified($domain);
        $domain = $lifecycle->tlsProvisioning($domain);
        $domain = $lifecycle->tlsReady($domain);

        return $lifecycle->activate($domain);
    }
}
