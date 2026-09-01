<?php

namespace Tests\Feature\Central;

use App\Commercial\Pricing\PricingCatalogBootstrapper;
use App\Enums\BillingAccountOwnerType;
use App\Enums\BillingInterval;
use App\Enums\ChurchActivationStatus;
use App\Enums\ChurchRole;
use App\Enums\DomainStatus;
use App\Enums\DomainTlsStatus;
use App\Enums\PlatformAuditEventType;
use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformMembershipStatus;
use App\Enums\PlatformRole;
use App\Filament\Central\Pages\ActivationDetail;
use App\Filament\Central\Pages\ProvisionChurch;
use App\Jobs\DeliverChurchActivationInvitation;
use App\Jobs\VerifyChurchDomain;
use App\Models\Church;
use App\Models\ChurchActivation;
use App\Models\ChurchDomain;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PlatformAuditEvent;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Onboarding\ChurchActivationTokenService;
use App\Onboarding\ProvisionChurchData;
use App\Platform\Operations\PlatformProvisionChurch;
use App\Platform\Operations\PlatformResendActivation;
use App\Platform\Operations\PlatformRetryDomain;
use App\Platform\Operations\PlatformRevokeActivation;
use App\Platform\PlatformStaffService;
use App\Support\OrganizationContext;
use App\Support\PlatformContext;
use App\Support\TenantContext;
use DomainException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlatformOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PricingCatalogBootstrapper::class)->bootstrap();
        Queue::fake();
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        parent::tearDown();
    }

    public function test_operations_role_provisions_canonically_idempotently_and_audits_without_customer_membership(): void
    {
        $this->asRole(PlatformRole::OPERATIONS);
        $key = (string) Str::uuid();
        $first = app(PlatformProvisionChurch::class)->execute($this->data($key), PlatformAuditReasonCategory::PLATFORM_ADMINISTRATION, 'Authorized onboarding request');
        $second = app(PlatformProvisionChurch::class)->execute($this->data($key), PlatformAuditReasonCategory::PLATFORM_ADMINISTRATION, 'Repeated browser request');
        $this->assertTrue($first->changed);
        $this->assertFalse($second->changed);
        $this->assertDatabaseCount('churches', 1);
        $this->assertDatabaseCount('church_activations', 1);
        $this->assertDatabaseCount('church_memberships', 0);
        $this->assertDatabaseCount('organization_memberships', 0);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('checkout_intents', 0);
        $activation = ChurchActivation::firstOrFail();
        $this->assertSame(ChurchActivationStatus::PENDING, $activation->status);
        $this->assertNotNull($activation->pricing_market_id);
        $this->assertFalse($activation->church->is_active);
        $this->assertSame(1, PlatformAuditEvent::where('event_type', PlatformAuditEventType::CHURCH_PROVISIONED)->count());
        $event = PlatformAuditEvent::where('event_type', PlatformAuditEventType::CHURCH_PROVISIONED)->firstOrFail();
        $this->assertSame('platform.operation.church.provision', $event->new_state['capability']);
        $this->assertSame('operations', $event->new_state['actor_role']);
        $this->assertFalse(app(TenantContext::class)->hasContext());
        $this->assertFalse(app(OrganizationContext::class)->hasContext());
    }

    public function test_unsupported_country_remains_commercial_review_without_delivery(): void
    {
        $this->asRole(PlatformRole::ADMINISTRATOR);
        $result = app(PlatformProvisionChurch::class)->execute($this->data((string) Str::uuid(), 'GB'), PlatformAuditReasonCategory::COMMERCIAL_CORRECTION, 'Market mapping requires review');
        $activation = ChurchActivation::findOrFail($result->targetId);
        $this->assertSame(ChurchActivationStatus::COMMERCIAL_REVIEW, $activation->status);
        $this->assertNull($activation->token_hash);
        $this->assertDatabaseCount('invitation_delivery_attempts', 0);
    }

    public function test_support_resend_rotates_token_queues_canonical_delivery_and_is_idempotent(): void
    {
        $activation = $this->provisionAsAdministrator();
        $old = app(ChurchActivationTokenService::class)->issue($activation);
        $oldHash = ChurchActivationTokenService::hash($old);
        $this->asRole(PlatformRole::SUPPORT);
        $correlation = (string) Str::uuid();
        $first = app(PlatformResendActivation::class)->execute($activation->id, PlatformAuditReasonCategory::DELIVERY_RECOVERY, 'Customer requested a fresh invitation', $correlation);
        $second = app(PlatformResendActivation::class)->execute($activation->id, PlatformAuditReasonCategory::DELIVERY_RECOVERY, 'Duplicate browser request', $correlation);
        $this->assertTrue($first->changed);
        $this->assertFalse($second->changed);
        $this->assertNotSame($oldHash, $activation->fresh()->token_hash);
        $this->assertDatabaseCount('invitation_delivery_attempts', 1);
        Queue::assertPushed(DeliverChurchActivationInvitation::class);
        $this->assertSame(1, PlatformAuditEvent::where('event_type', PlatformAuditEventType::ACTIVATION_INVITATION_RESENT)->count());
        $encoded = PlatformAuditEvent::where('event_type', PlatformAuditEventType::ACTIVATION_INVITATION_RESENT)->firstOrFail()->toJson();
        $this->assertStringNotContainsString($old, $encoded);
        $this->assertStringNotContainsString($activation->fresh()->token_hash, $encoded);

        $this->expectException(DomainException::class);
        app(PlatformResendActivation::class)->execute($activation->id, PlatformAuditReasonCategory::DELIVERY_RECOVERY, 'Concurrent distinct resend', (string) Str::uuid());
    }

    public function test_revocation_is_terminal_reasoned_and_idempotent_for_same_request(): void
    {
        $activation = $this->provisionAsAdministrator();
        $correlation = (string) Str::uuid();
        $result = app(PlatformRevokeActivation::class)->execute($activation->id, PlatformAuditReasonCategory::CUSTOMER_REQUEST, 'Customer withdrew activation', $correlation);
        $again = app(PlatformRevokeActivation::class)->execute($activation->id, PlatformAuditReasonCategory::CUSTOMER_REQUEST, 'Repeated browser request', $correlation);
        $this->assertTrue($result->changed);
        $this->assertFalse($again->changed);
        $this->assertSame(ChurchActivationStatus::REVOKED, $activation->fresh()->status);
        $this->assertNull($activation->fresh()->token_hash);
        $this->assertSame('Customer withdrew activation', PlatformAuditEvent::where('event_type', PlatformAuditEventType::ACTIVATION_REVOKED)->firstOrFail()->reason_note);
    }

    public function test_accepted_and_revoked_activations_cannot_be_mutated(): void
    {
        $accepted = $this->provisionAsAdministrator();
        $accepted->forceFill(['status' => ChurchActivationStatus::ACCEPTED, 'accepted_at' => now()])->save();
        $this->expectException(DomainException::class);
        app(PlatformRevokeActivation::class)->execute($accepted->id, PlatformAuditReasonCategory::CUSTOMER_REQUEST, 'Invalid terminal request', (string) Str::uuid());
    }

    public function test_domain_retry_uses_canonical_job_is_idempotent_and_fails_closed_without_provider(): void
    {
        $this->asRole(PlatformRole::SUPPORT);
        $church = Church::factory()->create();
        $domain = ChurchDomain::unguarded(fn () => ChurchDomain::create(['church_id' => $church->id, 'normalized_hostname' => 'retry.example.org', 'status' => DomainStatus::PendingVerification, 'verification_token_hash' => hash('sha256', 'secret'), 'tls_status' => DomainTlsStatus::NotStarted]));
        config()->set('public-website.custom_domains.dns_resolver', 'fake');
        $correlation = (string) Str::uuid();
        $first = app(PlatformRetryDomain::class)->execute($domain->id, PlatformAuditReasonCategory::PROVIDER_RECOVERY, 'Church corrected its DNS', $correlation);
        $second = app(PlatformRetryDomain::class)->execute($domain->id, PlatformAuditReasonCategory::PROVIDER_RECOVERY, 'Duplicate browser request', $correlation);
        $this->assertTrue($first->changed);
        $this->assertFalse($second->changed);
        Queue::assertPushed(VerifyChurchDomain::class, 1);
        $this->assertSame(1, PlatformAuditEvent::where('event_type', PlatformAuditEventType::DOMAIN_VERIFICATION_RETRY_REQUESTED)->count());
        config()->set('public-website.custom_domains.dns_resolver', 'unavailable');
        $this->expectException(DomainException::class);
        app(PlatformRetryDomain::class)->execute($domain->id, PlatformAuditReasonCategory::PROVIDER_RECOVERY, 'Retry without provider', (string) Str::uuid());
    }

    public function test_role_action_matrix_and_non_platform_identities_fail_closed(): void
    {
        $data = $this->data((string) Str::uuid());
        $this->asRole(PlatformRole::SUPPORT);
        $this->assertForbidden(fn () => app(PlatformProvisionChurch::class)->execute($data, PlatformAuditReasonCategory::CUSTOMER_REQUEST, 'Denied'));
        $this->asRole(PlatformRole::TRUST_SECURITY);
        $this->assertForbidden(fn () => app(PlatformProvisionChurch::class)->execute($data, PlatformAuditReasonCategory::CUSTOMER_REQUEST, 'Denied'));
        $this->asRole(PlatformRole::COMMERCIAL);
        $this->assertForbidden(fn () => app(PlatformProvisionChurch::class)->execute($data, PlatformAuditReasonCategory::CUSTOMER_REQUEST, 'Denied'));
        $this->actingAs(User::factory()->create());
        app(PlatformContext::class)->forgetResolved();
        $this->assertForbidden(fn () => app(PlatformProvisionChurch::class)->execute($data, PlatformAuditReasonCategory::CUSTOMER_REQUEST, 'Denied'));

        $church = Church::factory()->create();
        $churchOnly = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();
        $this->actingAs($churchOnly);
        app(PlatformContext::class)->forgetResolved();
        $this->assertForbidden(fn () => app(PlatformProvisionChurch::class)->execute($data, PlatformAuditReasonCategory::CUSTOMER_REQUEST, 'Denied'));
        $organization = Organization::create(['uuid' => (string) Str::uuid(), 'name' => 'Only Organization', 'slug' => 'only-organization', 'status' => 'active']);
        $organizationOnly = User::factory()->create();
        OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $organizationOnly->id, 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($organizationOnly);
        app(PlatformContext::class)->forgetResolved();
        $this->assertForbidden(fn () => app(PlatformProvisionChurch::class)->execute($data, PlatformAuditReasonCategory::CUSTOMER_REQUEST, 'Denied'));
    }

    public function test_view_and_unrelated_platform_capabilities_never_mutate_activation_or_domain(): void
    {
        $activation = $this->provisionAsAdministrator();
        $this->asRole(PlatformRole::COMMERCIAL);
        $this->assertForbidden(fn () => app(PlatformResendActivation::class)->execute($activation->id, PlatformAuditReasonCategory::DELIVERY_RECOVERY, 'Denied', (string) Str::uuid()));
        $this->asRole(PlatformRole::SUPPORT);
        $this->assertForbidden(fn () => app(PlatformRevokeActivation::class)->execute($activation->id, PlatformAuditReasonCategory::SECURITY_RESPONSE, 'Denied', (string) Str::uuid()));
        $this->asRole(PlatformRole::TRUST_SECURITY);
        $this->assertForbidden(fn () => app(PlatformRevokeActivation::class)->execute($activation->id, PlatformAuditReasonCategory::SECURITY_RESPONSE, 'Denied', (string) Str::uuid()));
    }

    public function test_operations_do_not_query_private_customer_domains(): void
    {
        $this->asRole(PlatformRole::OPERATIONS);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });
        app(PlatformProvisionChurch::class)->execute($this->data((string) Str::uuid()), PlatformAuditReasonCategory::PLATFORM_ADMINISTRATION, 'Privacy query proof');
        $sql = implode(' ', $queries);
        foreach (['prayer_requests', 'congregation_members', 'content_items', 'campaigns', 'media_assets'] as $table) {
            $this->assertStringNotContainsString($table, $sql);
        }
        $this->assertFalse(app(TenantContext::class)->hasContext());
        $this->assertFalse(app(OrganizationContext::class)->hasContext());
    }

    public function test_suspended_and_removed_platform_memberships_cannot_operate(): void
    {
        foreach ([PlatformMembershipStatus::SUSPENDED, PlatformMembershipStatus::REMOVED] as $status) {
            [$user,$membership] = $this->asRole(PlatformRole::OPERATIONS);
            $membership->forceFill(['status' => $status])->save();
            app(PlatformContext::class)->forgetResolved();
            $this->assertForbidden(fn () => app(PlatformProvisionChurch::class)->execute($this->data((string) Str::uuid()), PlatformAuditReasonCategory::CUSTOMER_REQUEST, 'Denied'));
        }
    }

    public function test_operation_runtime_has_no_customer_private_domain_dependencies_or_raw_updates(): void
    {
        $source = implode("\n", array_map('file_get_contents', glob(app_path('Platform/Operations/*.php'))));
        foreach (['PrayerRequest', 'CongregationMember', 'ContentItem', 'Campaign', 'MediaAsset', 'TenantContext', 'OrganizationContext'] as $name) {
            $this->assertStringNotContainsString($name, $source);
        }
        $this->assertStringNotContainsString('->update(', $source, 'raw update');
    }

    public function test_ui_hides_high_impact_actions_and_livewire_reauthorizes(): void
    {
        $activation = $this->provisionAsAdministrator();
        $this->asRole(PlatformRole::SUPPORT);
        Livewire::test(ActivationDetail::class, ['record' => $activation->id])
            ->assertSee('Resend invitation')->assertDontSee('Revoke activation')
            ->call('prepareOperation', 'resend')->set('reasonNote', 'Customer requested recovery')
            ->set('password', 'wrong-password')->call('runOperation')->assertForbidden();

        $this->asRole(PlatformRole::TRUST_SECURITY);
        Livewire::test(ActivationDetail::class, ['record' => $activation->id])->assertForbidden();
        Livewire::test(ProvisionChurch::class)->assertForbidden();
    }

    public function test_reason_is_required_at_service_boundary(): void
    {
        $activation = $this->provisionAsAdministrator();
        $this->expectException(DomainException::class);
        app(PlatformRevokeActivation::class)->execute($activation->id, PlatformAuditReasonCategory::CUSTOMER_REQUEST, '', (string) Str::uuid());
    }

    private function provisionAsAdministrator(): ChurchActivation
    {
        $this->asRole(PlatformRole::ADMINISTRATOR);
        $result = app(PlatformProvisionChurch::class)->execute($this->data((string) Str::uuid()), PlatformAuditReasonCategory::PLATFORM_ADMINISTRATION, 'Test provision');

        return ChurchActivation::findOrFail($result->targetId);
    }

    private function data(string $key, string $country = 'NG'): ProvisionChurchData
    {
        return new ProvisionChurchData('platform-test', 'central-test', $key, 'Governed Church', null, $country, 'Africa/Lagos', 'primary@example.test', BillingInterval::MONTHLY, BillingAccountOwnerType::CHURCH);
    }

    /** @return array{User,PlatformMembership} */
    private function asRole(PlatformRole $role): array
    {
        $user = User::factory()->create();
        $membership = app(PlatformStaffService::class)->create($user, $role, null, PlatformAuditReasonCategory::PLATFORM_ADMINISTRATION, 'Test fixture');
        $this->actingAs($user);
        app(PlatformContext::class)->forgetResolved();

        return [$user, $membership];
    }

    private function assertForbidden(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Operation should be forbidden.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
