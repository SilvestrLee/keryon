<?php

namespace Tests\Feature\Domains;

use App\Domain\ChurchDomainLifecycle;
use App\Domain\Dns\DnsLookupResult;
use App\Domain\Dns\DnsResolver;
use App\Domain\Dns\FakeDnsResolver;
use App\Domain\DomainNameNormalizer;
use App\Domain\MakeChurchDomainPrimary;
use App\Domain\RegenerateChurchDomainToken;
use App\Domain\RequestChurchCustomDomain;
use App\Domain\VerifyChurchDomainOwnership;
use App\Domain\VerifyChurchDomainRouting;
use App\Enums\Capability;
use App\Enums\ChurchDomainEventType;
use App\Enums\ChurchRole;
use App\Enums\DomainStatus;
use App\Enums\MembershipStatus;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\ChurchMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\WebsitePublication;
use App\Support\TenantContext;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChurchDomainGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_hostname_normalization_and_platform_protection_are_bounded(): void
    {
        $normalizer = app(DomainNameNormalizer::class);

        $this->assertSame('www.example.org', $normalizer->normalize('  WWW.Example.ORG. '));
        $this->assertNotSame($normalizer->normalize('www.example.org'), $normalizer->normalize('example.org'));

        foreach ([
            'https://church.org', 'church.org/path', 'church.org:443', '*.church.org',
            '127.0.0.1', 'localhost', 'café.org', 'xn--caf-dma.org', 'keryon.app',
            'app.keryon.app', 'another-church.keryon.app', '-bad.example', 'bad-.example',
        ] as $invalid) {
            try {
                $normalizer->normalize($invalid);
                $this->fail("Expected [{$invalid}] to be rejected.");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_only_primary_administrator_may_claim_and_manage_domains(): void
    {
        $church = Church::create(['name' => 'Governed Church', 'slug' => 'governed-church', 'activated_at' => now()]);
        $primaryAdmin = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($primaryAdmin);

        $membership = app(TenantContext::class)->currentMembership();
        $this->assertTrue($membership->hasCapability(Capability::WebsiteDomainManage));
        $claim = app(RequestChurchCustomDomain::class)->execute($church, 'WWW.Governed.org.');

        $this->assertSame('www.governed.org', $claim->domain->normalized_hostname);
        $this->assertNotSame($claim->verificationToken, $claim->domain->verification_token_hash);
        $this->assertSame(hash('sha256', $claim->verificationToken), $claim->domain->verification_token_hash);
        $this->assertTrue(Gate::forUser($primaryAdmin)->allows('update', $claim->domain));

        foreach ([
            [ChurchRole::ADMINISTRATOR, false],
            [ChurchRole::COMMUNICATIONS, true],
            [ChurchRole::CARE, true],
        ] as [$role, $primary]) {
            $user = User::factory()->forChurch($church, [$role], primary: $primary)->create();
            $this->actingAs($user);
            app(TenantContext::class)->forgetResolved();
            try {
                app(RequestChurchCustomDomain::class)->execute($church, fake()->unique()->domainName());
                $this->fail('Unauthorized membership claimed a domain.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_organization_authority_and_cross_church_ids_never_grant_domain_authority(): void
    {
        $churchA = Church::create(['name' => 'A', 'slug' => 'church-a']);
        $churchB = Church::create(['name' => 'B', 'slug' => 'church-b']);
        $user = User::factory()->forChurch($churchA, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $organization = Organization::create(['uuid' => fake()->uuid(), 'name' => 'Org', 'slug' => 'org', 'status' => 'active']);
        OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($user);

        $this->expectException(AuthorizationException::class);
        app(RequestChurchCustomDomain::class)->execute($churchB, 'church-b.org');
    }

    public function test_organization_only_suspended_removed_and_manipulated_domain_access_fail_closed(): void
    {
        $church = Church::create(['name' => 'Protected', 'slug' => 'protected', 'is_active' => true]);
        $organizationOnly = User::factory()->create();
        $organization = Organization::create(['uuid' => fake()->uuid(), 'name' => 'Only Org', 'slug' => 'only-org', 'status' => 'active']);
        OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $organizationOnly->id, 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($organizationOnly);
        $this->assertThrows(fn () => app(RequestChurchCustomDomain::class)->execute($church, 'organization-only.org'), AuthorizationException::class);

        foreach ([MembershipStatus::SUSPENDED, MembershipStatus::REMOVED] as $status) {
            $user = User::factory()->create();
            $membership = ChurchMembership::factory()->for($church)->for($user)->state(['status' => $status, 'is_primary' => true])->create();
            $membership->assignRoles([ChurchRole::ADMINISTRATOR]);
            $this->actingAs($user);
            app(TenantContext::class)->forgetResolved();
            $this->assertThrows(fn () => app(RequestChurchCustomDomain::class)->execute($church, $status->value.'.example.org'), AuthorizationException::class);
        }

        [$otherChurch, $admin] = $this->primaryAdministrator();
        $this->actingAs($admin);
        app(TenantContext::class)->forgetResolved();
        $otherDomain = app(RequestChurchCustomDomain::class)->execute($otherChurch, 'other-protected.org')->domain;
        $foreignUser = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($foreignUser);
        app(TenantContext::class)->forgetResolved();
        $this->assertFalse(Gate::forUser($foreignUser)->allows('update', $otherDomain));
        $this->assertThrows(fn () => app(MakeChurchDomainPrimary::class)->execute($otherDomain), AuthorizationException::class);
    }

    public function test_claim_limit_global_uniqueness_quarantine_and_event_evidence(): void
    {
        [$church, $user] = $this->primaryAdministrator();
        $this->actingAs($user);
        $service = app(RequestChurchCustomDomain::class);

        $first = $service->execute($church, 'church.org');
        $second = $service->execute($church, 'www.church.org');
        $this->assertCount(2, ChurchDomain::query()->get());
        $this->assertDatabaseHas('church_domain_events', ['church_domain_id' => $first->domain->id, 'event_type' => ChurchDomainEventType::Requested->value]);

        try {
            $service->execute($church, 'third.church.org');
            $this->fail('A third active claim was accepted.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        app(ChurchDomainLifecycle::class)->release($second->domain, app(TenantContext::class)->currentMembership());
        $other = Church::create(['name' => 'Other', 'slug' => 'other']);
        $otherUser = User::factory()->forChurch($other, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($otherUser);
        app(TenantContext::class)->forgetResolved();

        try {
            $service->execute($other, 'www.church.org');
            $this->fail('Released hostname was reassigned automatically.');
        } catch (ValidationException) {
            $this->assertNotNull($second->domain->fresh()->released_at);
        }
    }

    public function test_verification_token_regeneration_and_dns_checks_are_separate_and_retryable(): void
    {
        [$church, $user] = $this->primaryAdministrator();
        $this->actingAs($user);
        $claim = app(RequestChurchCustomDomain::class)->execute($church, 'www.verify.org');
        $dns = new FakeDnsResolver;
        $this->app->instance(DnsResolver::class, $dns);

        $dns->setTxtResult($claim->verificationHostname, DnsLookupResult::timeout());
        $this->assertFalse(app(VerifyChurchDomainOwnership::class)->execute($claim->domain));
        $this->assertSame(DomainStatus::PendingVerification, $claim->domain->fresh()->status);

        $regenerated = app(RegenerateChurchDomainToken::class)->execute($claim->domain->fresh());
        $this->assertNotSame($claim->verificationToken, $regenerated->verificationToken);
        $dns->setTxt($regenerated->verificationHostname, [$claim->verificationToken]);
        $this->assertFalse(app(VerifyChurchDomainOwnership::class)->execute($regenerated->domain));
        $dns->setTxt($regenerated->verificationHostname, [$regenerated->verificationToken]);
        $this->assertTrue(app(VerifyChurchDomainOwnership::class)->execute($regenerated->domain));
        $this->assertNull($regenerated->domain->fresh()->routing_verified_at);

        config()->set('public-website.custom_domains.dns_ingress_target', 'target.keryon.app');
        $dns->setCname('www.verify.org', ['target.keryon.app.']);
        $this->assertTrue(app(VerifyChurchDomainRouting::class)->execute($regenerated->domain->fresh()));
        $this->assertSame(DomainStatus::Verified, $regenerated->domain->fresh()->status);
    }

    public function test_tls_activation_primary_switch_disable_and_release_are_gated(): void
    {
        [$church, $user] = $this->primaryAdministrator();
        $this->actingAs($user);
        $claim = app(RequestChurchCustomDomain::class)->execute($church, 'primary.org');
        $lifecycle = app(ChurchDomainLifecycle::class);
        $membership = app(TenantContext::class)->currentMembership();

        $this->expectException(ValidationException::class);
        $lifecycle->activate($claim->domain, $membership);
    }

    public function test_fully_verified_domain_can_be_activated_and_only_one_can_be_primary(): void
    {
        [$church, $user] = $this->primaryAdministrator();
        $this->actingAs($user);
        $service = app(RequestChurchCustomDomain::class);
        $lifecycle = app(ChurchDomainLifecycle::class);
        $membership = app(TenantContext::class)->currentMembership();

        $first = $this->activate($service->execute($church, 'one.org')->domain, $lifecycle);
        $second = $this->activate($service->execute($church, 'two.org')->domain, $lifecycle);
        app(MakeChurchDomainPrimary::class)->execute($first);
        app(MakeChurchDomainPrimary::class)->execute($second);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame(1, ChurchDomain::query()->where('is_primary', true)->count());

        $lifecycle->disable($second->fresh(), $membership);
        $this->assertFalse($second->fresh()->is_primary);
        $lifecycle->release($second->fresh(), $membership);
        $this->assertSame(DomainStatus::Released, $second->fresh()->status);
        $this->assertNotNull($second->fresh()->released_at);
    }

    public function test_activated_or_published_church_slug_is_immutable_but_name_may_change(): void
    {
        $church = Church::create(['name' => 'Stable', 'slug' => 'stable', 'activated_at' => now()]);
        $church->update(['name' => 'A New Display Name']);
        $this->assertSame('stable', $church->fresh()->slug);

        try {
            $church->update(['slug' => 'changed']);
            $this->fail('Activated Church slug changed.');
        } catch (DomainException) {
            $this->assertSame('stable', $church->fresh()->slug);
        }

        $provisional = Church::create(['name' => 'Provisional', 'slug' => 'provisional']);
        $provisional->update(['slug' => 'provisional-updated']);
        $this->assertSame('provisional-updated', $provisional->fresh()->slug);

        $published = Church::create(['name' => 'Historical', 'slug' => 'historical']);
        WebsitePublication::withoutGlobalScope('church_tenant')->forceCreate([
            'church_id' => $published->id, 'destination' => 'church_website', 'theme' => 'proclaim',
            'snapshot' => [], 'working_fingerprint' => str_repeat('a', 64), 'published_at' => now(),
        ]);
        $this->expectException(DomainException::class);
        $published->update(['slug' => 'historical-changed']);
    }

    /** @return array{Church,User} */
    private function primaryAdministrator(): array
    {
        $church = Church::create(['name' => 'Domain Church', 'slug' => fake()->unique()->slug(2), 'activated_at' => now()]);
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();

        return [$church, $user];
    }

    private function activate(ChurchDomain $domain, ChurchDomainLifecycle $lifecycle): ChurchDomain
    {
        $domain = $lifecycle->ownershipVerified($domain);
        $domain = $lifecycle->routingVerified($domain);
        $domain = $lifecycle->tlsProvisioning($domain);
        $domain = $lifecycle->tlsReady($domain);

        return $lifecycle->activate($domain);
    }
}
