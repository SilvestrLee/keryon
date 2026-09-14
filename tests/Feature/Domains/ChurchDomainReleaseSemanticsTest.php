<?php

namespace Tests\Feature\Domains;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Domain\ChurchDomainLifecycle;
use App\Domain\MakeChurchDomainPrimary;
use App\Domain\ReleaseChurchDomain;
use App\Domain\RequestChurchCustomDomain;
use App\Enums\ChurchDomainEventType;
use App\Enums\ChurchRole;
use App\Enums\DomainStatus;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

/**
 * K-DOMAIN-001F §20-§25, §29 — truthful released-domain semantics: no
 * 30-day quarantine/expiry exists, released hostnames are retained
 * globally and are never self-service reclaimable, and no copy anywhere
 * implies a timer, countdown, or automatic return.
 */
class ChurchDomainReleaseSemanticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // This file exercises release/reclaim/copy semantics, not
        // entitlement gating — allow every entitlement so claim/activate
        // fixtures succeed. Entitlement enforcement itself is covered by
        // CustomDomainEntitlementTest.
        $entitlements = Mockery::mock(EntitlementResolver::class);
        $entitlements->shouldReceive('allows')->andReturnTrue();
        $this->app->instance(EntitlementResolver::class, $entitlements);
    }

    public function test_release_preserves_the_row_sets_status_and_time_and_clears_primary(): void
    {
        [$church] = $this->primaryAdministrator();
        $domain = $this->activeDomain($church, 'preserved.example.org');
        app(MakeChurchDomainPrimary::class)->execute($domain->fresh());
        $this->assertTrue($domain->fresh()->is_primary);

        $released = app(ReleaseChurchDomain::class)->execute($domain->fresh());

        $this->assertDatabaseHas('church_domains', ['id' => $domain->id]);
        $this->assertSame(DomainStatus::Released, $released->status);
        $this->assertNotNull($released->released_at);
        $this->assertFalse($released->is_primary);
    }

    public function test_release_history_and_events_remain(): void
    {
        [$church] = $this->primaryAdministrator();
        $domain = $this->activeDomain($church, 'history.example.org');
        $eventsBefore = DB::table('church_domain_events')->where('church_domain_id', $domain->id)->count();

        app(ReleaseChurchDomain::class)->execute($domain->fresh());

        $this->assertGreaterThan($eventsBefore, DB::table('church_domain_events')->where('church_domain_id', $domain->id)->count());
        $this->assertDatabaseHas('church_domain_events', ['church_domain_id' => $domain->id, 'event_type' => ChurchDomainEventType::Released->value]);
    }

    public function test_same_church_cannot_self_reclaim_a_released_hostname(): void
    {
        [$church, $user] = $this->primaryAdministrator();
        $domain = $this->activeDomain($church, 'same-church-reclaim.example.org');
        app(ReleaseChurchDomain::class)->execute($domain->fresh());
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();

        $this->expectException(ValidationException::class);
        app(RequestChurchCustomDomain::class)->execute($church, 'same-church-reclaim.example.org');
    }

    public function test_another_church_cannot_self_reclaim_a_released_hostname(): void
    {
        [$churchA] = $this->primaryAdministrator();
        $domain = $this->activeDomain($churchA, 'other-church-reclaim.example.org');
        app(ReleaseChurchDomain::class)->execute($domain->fresh());

        [$churchB, $userB] = $this->primaryAdministrator();
        $this->actingAs($userB);
        app(TenantContext::class)->forgetResolved();

        $this->expectException(ValidationException::class);
        app(RequestChurchCustomDomain::class)->execute($churchB, 'other-church-reclaim.example.org');
    }

    public function test_claim_conflict_copy_is_truthful_and_does_not_leak_another_church(): void
    {
        [$church, $user] = $this->primaryAdministrator();
        $domain = $this->activeDomain($church, 'truthful-copy.example.org');
        app(ReleaseChurchDomain::class)->execute($domain->fresh());
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();

        try {
            app(RequestChurchCustomDomain::class)->execute($church, 'truthful-copy.example.org');
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['hostname'][0];
            $this->assertStringNotContainsString('quarantine', strtolower($message));
            $this->assertStringNotContainsString('30-day', strtolower($message));
            $this->assertStringNotContainsString('30 day', strtolower($message));
            $this->assertStringContainsString('cannot currently be claimed', $message);
            // No Church identity of any kind is disclosed.
            $this->assertStringNotContainsString((string) $church->id, $message);
            $this->assertStringNotContainsString($church->name, $message);
        }
    }

    public function test_released_records_do_not_consume_the_active_claim_limit(): void
    {
        [$church, $user] = $this->primaryAdministrator();
        $first = $this->activeDomain($church, 'limit-one.example.org');
        $second = $this->activeDomain($church, 'limit-two.example.org');
        app(ReleaseChurchDomain::class)->execute($first->fresh());
        app(ReleaseChurchDomain::class)->execute($second->fresh());
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();

        // Both prior slots are released — two brand-new claims must succeed,
        // proving released rows are excluded from the active claim count.
        $third = app(RequestChurchCustomDomain::class)->execute($church, 'limit-three.example.org');
        $fourth = app(RequestChurchCustomDomain::class)->execute($church, 'limit-four.example.org');

        $this->assertNotNull($third->domain->id);
        $this->assertNotNull($fourth->domain->id);
    }

    public function test_no_active_configuration_advertises_a_30_day_release_window(): void
    {
        $this->assertArrayNotHasKey('quarantine_days', config('public-website.custom_domains'));
    }

    public function test_no_backend_error_copy_references_quarantine(): void
    {
        $source = file_get_contents(app_path('Domain/RequestChurchCustomDomain.php'));

        $this->assertStringNotContainsStringIgnoringCase('quarantine', $source);
    }

    public function test_no_ui_copy_references_30_day_quarantine(): void
    {
        $source = file_get_contents(resource_path('views/filament/clusters/website/pages/manage-domains.blade.php'));

        $this->assertStringNotContainsStringIgnoringCase('quarantine', $source);
        $this->assertStringNotContainsStringIgnoringCase('30-day', $source);
        $this->assertStringNotContainsStringIgnoringCase('30 day', $source);
    }

    /** @return array{Church,User} */
    private function primaryAdministrator(): array
    {
        $church = Church::create(['name' => 'Release Church', 'slug' => fake()->unique()->slug(2), 'activated_at' => now()]);
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($user);

        return [$church, $user];
    }

    private function activeDomain(Church $church, string $hostname): ChurchDomain
    {
        $claim = app(RequestChurchCustomDomain::class)->execute($church, $hostname);
        $lifecycle = app(ChurchDomainLifecycle::class);
        $domain = $lifecycle->ownershipVerified($claim->domain);
        $domain = $lifecycle->routingVerified($domain);
        $domain = $lifecycle->tlsProvisioning($domain);
        $domain = $lifecycle->tlsReady($domain);

        return $lifecycle->activate($domain);
    }
}
