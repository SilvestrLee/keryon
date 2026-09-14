<?php

namespace Tests\Feature\Domains;

use App\Domain\DomainNameNormalizer;
use App\Models\Church;
use App\Onboarding\ChurchSlugService;
use App\PublicWebsite\PublicWebsiteHostResolver;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * K-DOMAIN-001F §15-§19, §28 — the canonical reserved Church-slug policy.
 */
class ChurchSlugReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_church_slug_is_accepted(): void
    {
        $slug = app(ChurchSlugService::class)->available('Grace Community Church');

        $this->assertSame('grace-community-church', $slug);
    }

    public function test_each_configured_reserved_label_is_rejected_when_explicit(): void
    {
        $service = app(ChurchSlugService::class);

        foreach (config('public-website.reserved_subdomains') as $label) {
            try {
                $service->available($label, explicit: true);
                $this->fail("Expected reserved label [{$label}] to be rejected.");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_non_explicit_reserved_label_is_disambiguated_rather_than_silently_assigned(): void
    {
        $slug = app(ChurchSlugService::class)->available('app');

        $this->assertNotSame('app', $slug);
        $this->assertSame('app-church', $slug);
    }

    public function test_uppercase_and_mixed_case_reserved_labels_cannot_bypass_reservation(): void
    {
        $service = app(ChurchSlugService::class);

        foreach (['APP', 'Admin', 'StAgInG', 'ORIGIN'] as $variant) {
            try {
                $service->available($variant, explicit: true);
                $this->fail("Expected case-variant reserved label [{$variant}] to be rejected.");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_direct_model_creation_with_a_reserved_slug_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        Church::create(['name' => 'Sneaky', 'slug' => 'staging']);
    }

    public function test_direct_model_creation_with_uppercase_reserved_slug_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        Church::create(['name' => 'Sneaky Upper', 'slug' => 'STAGING']);
    }

    public function test_provisional_slug_edit_to_a_reserved_label_is_rejected(): void
    {
        $church = Church::create(['name' => 'Provisional', 'slug' => 'provisional-church']);

        $this->expectException(DomainException::class);
        $church->update(['slug' => 'origin']);
    }

    public function test_direct_service_write_bypassing_church_slug_service_still_cannot_assign_a_reserved_slug(): void
    {
        // No ChurchSlugService involved at all — a raw Eloquent write.
        $church = new Church(['name' => 'Raw Write', 'slug' => 'central']);

        $this->expectException(DomainException::class);
        $church->save();
    }

    public function test_activated_church_slug_remains_immutable_independent_of_reserved_policy(): void
    {
        $church = Church::create(['name' => 'Activated', 'slug' => 'activated-church', 'activated_at' => now()]);

        try {
            $church->update(['slug' => 'not-reserved-but-activated']);
            $this->fail('An activated Church slug changed.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('stable public infrastructure identity', $exception->getMessage());
        }
    }

    public function test_reserved_label_list_is_sourced_canonically_not_duplicated(): void
    {
        // Both consumers must read the exact same config source — proven by
        // changing it once and observing both react.
        config()->set('public-website.reserved_subdomains', ['totally-custom-reserved-label']);

        $this->expectException(DomainException::class);
        app(ChurchSlugService::class)->available('totally-custom-reserved-label', explicit: true);
    }

    public function test_reserved_label_source_change_is_also_observed_by_the_church_model_guard(): void
    {
        config()->set('public-website.reserved_subdomains', ['totally-custom-reserved-label']);

        $this->expectException(DomainException::class);
        Church::create(['name' => 'Custom Reserved', 'slug' => 'totally-custom-reserved-label']);
    }

    public function test_app_dot_example_dot_org_remains_valid_as_an_external_custom_domain(): void
    {
        // "app" is reserved under keryon.app; it must not make an unrelated
        // external domain invalid merely because it shares that label (§19).
        $normalized = app(DomainNameNormalizer::class)->normalize('app.example.org');

        $this->assertSame('app.example.org', $normalized);
    }

    public function test_reserved_keryon_subdomains_never_resolve_to_a_church(): void
    {
        // No Church can ever hold a reserved slug (proven above), so these
        // platform-labeled hosts must resolve to nothing.
        foreach (config('public-website.reserved_subdomains') as $label) {
            $host = $label.'.'.config('public-website.base_domain');
            $resolved = app(PublicWebsiteHostResolver::class)->resolve($host);

            $this->assertNull($resolved, "Expected platform host [{$host}] not to resolve to a Church.");
        }
    }
}
