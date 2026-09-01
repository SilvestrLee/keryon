<?php

namespace Tests\Feature\Central;

use App\Enums\ChurchRole;
use App\Enums\PlatformAuditEventType;
use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformAuditTargetType;
use App\Enums\PlatformCapability;
use App\Enums\PlatformMembershipStatus;
use App\Enums\PlatformRole;
use App\Enums\WorkspaceType;
use App\Filament\Central\Pages\CentralHome;
use App\Filament\Central\Pages\PlatformAudit;
use App\Filament\Central\Pages\PlatformStaff;
use App\Http\Middleware\RequireCentralMfaReadiness;
use App\Livewire\KeryonWorkspaceHeader;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\OrganizationMembership;
use App\Models\PlatformAuditEvent;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationIdentityService;
use App\Platform\PlatformAudit as PlatformAuditRecorder;
use App\Platform\PlatformStaffService;
use App\Search\GlobalSearchService;
use App\Support\OrganizationContext;
use App\Support\PlatformContext;
use App\Support\TenantContext;
use App\Workspace\WorkspaceRegistry;
use DomainException;
use Filament\Facades\Filament;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlatformFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        parent::tearDown();
    }

    public function test_central_admission_requires_verified_active_platform_membership(): void
    {
        [$user] = $this->platformUser(PlatformRole::SUPPORT);
        app(PlatformContext::class)->forgetResolved();
        $this->actingAs($user);
        $this->assertTrue(app(PlatformContext::class)->hasContext());
        $this->assertTrue(CentralHome::canAccess());
        $response = $this->get('/central/central-home');
        $response->assertOk()->assertSee('Keryon Central')->assertSee('Platform Operations');
    }

    public function test_missing_platform_authority_is_denied(): void
    {
        $none = User::factory()->create();
        $this->actingAs($none)->get('/central/central-home')->assertForbidden();
    }

    public function test_unverified_platform_identity_is_denied(): void
    {
        $unverified = User::factory()->unverified()->create();
        PlatformMembership::create(['user_id' => $unverified->id, 'role' => PlatformRole::ADMINISTRATOR, 'status' => PlatformMembershipStatus::ACTIVE, 'activated_at' => now()]);
        app(PlatformContext::class)->forgetResolved();
        $this->actingAs($unverified)->get('/central/central-home')->assertForbidden();
    }

    public function test_suspended_platform_authority_is_denied(): void
    {
        $user = User::factory()->create();
        PlatformMembership::create(['user_id' => $user->id, 'role' => PlatformRole::ADMINISTRATOR, 'status' => PlatformMembershipStatus::SUSPENDED, 'activated_at' => now(), 'suspended_at' => now()]);
        app(PlatformContext::class)->forgetResolved();
        $this->actingAs($user)->get('/central/central-home')->assertForbidden();
    }

    public function test_removed_platform_authority_is_denied(): void
    {
        $user = User::factory()->create();
        PlatformMembership::create(['user_id' => $user->id, 'role' => PlatformRole::ADMINISTRATOR, 'status' => PlatformMembershipStatus::REMOVED, 'activated_at' => now(), 'removed_at' => now()]);
        app(PlatformContext::class)->forgetResolved();
        $this->actingAs($user)->get('/central/central-home')->assertForbidden();
    }

    public function test_each_role_has_exactly_the_approved_capabilities(): void
    {
        $expected = [
            PlatformRole::ADMINISTRATOR->value => PlatformCapability::cases(),
            PlatformRole::OPERATIONS->value => [PlatformCapability::PlatformHomeView, PlatformCapability::ChurchesView, PlatformCapability::ChurchesProvision, PlatformCapability::ActivationsView, PlatformCapability::ActivationsManage, PlatformCapability::OrganizationsView, PlatformCapability::OrganizationsProvision, PlatformCapability::AssignmentsView, PlatformCapability::SubscriptionsView, PlatformCapability::PricingView, PlatformCapability::DomainsView, PlatformCapability::DomainsManage, PlatformCapability::DeliveriesView, PlatformCapability::DeliveriesManage, PlatformCapability::ProvidersView, PlatformCapability::PlatformAuditView, PlatformCapability::PlatformChurchProvision, PlatformCapability::PlatformActivationResend, PlatformCapability::PlatformActivationRevoke, PlatformCapability::PlatformDomainRetry],
            PlatformRole::SUPPORT->value => [PlatformCapability::PlatformHomeView, PlatformCapability::ChurchesView, PlatformCapability::ActivationsView, PlatformCapability::OrganizationsView, PlatformCapability::AssignmentsView, PlatformCapability::DomainsView, PlatformCapability::DeliveriesView, PlatformCapability::ProvidersView, PlatformCapability::PlatformActivationResend, PlatformCapability::PlatformDomainRetry],
            PlatformRole::COMMERCIAL->value => [PlatformCapability::PlatformHomeView, PlatformCapability::ChurchesView, PlatformCapability::ActivationsView, PlatformCapability::ActivationsManage, PlatformCapability::OrganizationsView, PlatformCapability::AssignmentsView, PlatformCapability::SubscriptionsView, PlatformCapability::SubscriptionsManage, PlatformCapability::PricingView, PlatformCapability::PricingManage, PlatformCapability::BillingView, PlatformCapability::BillingManage, PlatformCapability::ProvidersView, PlatformCapability::PlatformAuditView],
            PlatformRole::TRUST_SECURITY->value => [PlatformCapability::PlatformHomeView, PlatformCapability::DomainsView, PlatformCapability::ProvidersView, PlatformCapability::ProvidersManage, PlatformCapability::TrustView, PlatformCapability::TrustManage, PlatformCapability::PlatformAuditView],
        ];

        foreach (PlatformRole::cases() as $role) {
            $this->assertEqualsCanonicalizing($expected[$role->value], $role->capabilities(), $role->value);
            foreach (PlatformCapability::cases() as $capability) {
                $this->assertSame(in_array($capability, $expected[$role->value], true), $role->hasCapability($capability), $role->value.' '.$capability->value);
            }
        }
    }

    public function test_platform_context_is_identity_safe_request_scoped_and_customer_contexts_remain_empty(): void
    {
        [$first, $firstMembership] = $this->platformUser(PlatformRole::OPERATIONS);
        [$second, $secondMembership] = $this->platformUser(PlatformRole::SUPPORT);
        $this->actingAs($first)->withSession(['active_workspace_type' => 'central']);
        $this->assertSame($firstMembership->id, app(PlatformContext::class)->currentMembership()?->id);
        $this->assertFalse(app(TenantContext::class)->hasContext());
        $this->assertFalse(app(OrganizationContext::class)->hasContext());
        $this->actingAs($second);
        $this->assertSame($secondMembership->id, app(PlatformContext::class)->currentMembership()?->id);
    }

    public function test_platform_membership_is_unique_and_never_creates_customer_memberships(): void
    {
        [$user] = $this->platformUser(PlatformRole::ADMINISTRATOR);
        $this->assertSame(0, ChurchMembership::where('user_id', $user->id)->count());
        $this->assertSame(0, OrganizationMembership::where('user_id', $user->id)->count());
        $this->expectException(UniqueConstraintViolationException::class);
        PlatformMembership::create(['user_id' => $user->id, 'role' => PlatformRole::SUPPORT, 'status' => PlatformMembershipStatus::ACTIVE, 'activated_at' => now()]);
    }

    public function test_customer_memberships_do_not_grant_central_and_platform_only_does_not_enter_customer_planes(): void
    {
        $church = Church::factory()->create();
        $churchUser = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();
        $this->actingAs($churchUser)->get('/central/central-home')->assertForbidden();

        $organization = app(OrganizationHierarchyService::class)->createOrganization('Bounded Organization', 'bounded');
        $organizationUser = User::factory()->create();
        app(OrganizationIdentityService::class)->bootstrapAdministrator($organization, $organizationUser);
        app(PlatformContext::class)->forgetResolved();
        $this->actingAs($organizationUser)->get('/central/central-home')->assertForbidden();

        [$platformUser] = $this->platformUser(PlatformRole::ADMINISTRATOR);
        app(PlatformContext::class)->forgetResolved();
        $this->actingAs($platformUser)->get('/admin')->assertForbidden();
        $this->get('/organization/organization-overview')->assertForbidden();
    }

    public function test_workspace_registry_and_switching_require_direct_memberships_and_clear_contexts(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();
        $organization = app(OrganizationHierarchyService::class)->createOrganization('Three Plane Organization', 'three-plane');
        app(OrganizationIdentityService::class)->bootstrapAdministrator($organization, $user);
        $membership = PlatformMembership::create(['user_id' => $user->id, 'role' => PlatformRole::ADMINISTRATOR, 'status' => PlatformMembershipStatus::ACTIVE, 'activated_at' => now()]);
        $this->actingAs($user)->withSession(['active_church_id' => $church->id, 'active_workspace_type' => 'church']);

        $this->assertEqualsCanonicalizing(['church', 'organization', 'central'], app(WorkspaceRegistry::class)->for($user, WorkspaceType::Church)->pluck('type.value')->all());
        $this->post(route('workspace.switch', ['type' => 'central', 'workspace' => $membership->id]))->assertRedirect('/central');
        $this->assertSame('central', session('active_workspace_type'));
        $this->assertFalse(session()->has('active_church_id'));
        $this->assertFalse(session()->has('active_organization_id'));
        $this->assertFalse(app(TenantContext::class)->hasContext());
        $this->assertFalse(app(OrganizationContext::class)->hasContext());

        $this->post(route('workspace.switch', ['type' => 'church', 'workspace' => $church->id]))->assertRedirect('/admin');
        $this->assertSame('church', session('active_workspace_type'));
        app(PlatformContext::class)->forgetResolved();
        $this->assertNotSame('central', session('active_workspace_type'));

        $this->post(route('workspace.switch', ['type' => 'organization', 'workspace' => $organization->id]))->assertRedirect('/organization');
        $this->assertSame('organization', session('active_workspace_type'));
    }

    public function test_staff_service_audits_and_protects_self_and_final_administrator(): void
    {
        [$admin, $actor] = $this->platformUser(PlatformRole::ADMINISTRATOR);
        $targetUser = User::factory()->create();
        $staff = app(PlatformStaffService::class);
        $target = $staff->create($targetUser, PlatformRole::SUPPORT, $actor, PlatformAuditReasonCategory::PLATFORM_ADMINISTRATION, 'Launch support access');
        $this->assertSame(2, PlatformAuditEvent::count());
        $this->assertSame($actor->id, PlatformAuditEvent::latest('id')->first()->platform_membership_id);

        $staff->suspend($target, $actor, PlatformAuditReasonCategory::SECURITY_RESPONSE, 'Access review required');
        $this->assertSame(PlatformMembershipStatus::SUSPENDED, $target->fresh()->status);
        $this->expectException(DomainException::class);
        $staff->remove($actor, $actor, PlatformAuditReasonCategory::PLATFORM_ADMINISTRATION, 'Self removal');
    }

    public function test_final_active_administrator_cannot_be_suspended_or_removed(): void
    {
        [$admin, $membership] = $this->platformUser(PlatformRole::ADMINISTRATOR);
        $otherUser = User::factory()->create();
        $other = PlatformMembership::create(['user_id' => $otherUser->id, 'role' => PlatformRole::OPERATIONS, 'status' => PlatformMembershipStatus::ACTIVE, 'activated_at' => now()]);
        $this->expectException(DomainException::class);
        app(PlatformStaffService::class)->suspend($membership, $other, PlatformAuditReasonCategory::SECURITY_RESPONSE, 'Invalid actor and final admin');
    }

    public function test_audit_rows_are_immutable_and_payload_is_bounded(): void
    {
        [, $actor] = $this->platformUser(PlatformRole::ADMINISTRATOR);
        $event = PlatformAuditEvent::firstOrFail();
        $this->assertEqualsCanonicalizing(['user_id', 'role', 'status'], array_keys($event->new_state));
        $this->assertStringNotContainsString('password', json_encode($event->new_state));
        try {
            $event->forceFill(['reason_note' => 'changed'])->save();
            $this->fail('Audit update should fail.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_audit_policy_denies_mutation_and_recorder_rejects_non_allowlisted_state(): void
    {
        [$admin, $actor] = $this->platformUser(PlatformRole::ADMINISTRATOR);
        $event = PlatformAuditEvent::firstOrFail();
        $this->actingAs($admin);
        app(PlatformContext::class)->forgetResolved();
        $this->assertFalse($admin->can('update', $event));
        $this->assertFalse($admin->can('delete', $event));

        $this->expectException(DomainException::class);
        PlatformAuditRecorder::record(
            PlatformAuditEventType::PLATFORM_ACCESS_GRANTED,
            PlatformAuditTargetType::PLATFORM_MEMBERSHIP,
            $actor->id,
            $actor,
            null,
            ['provider_secret' => 'must never persist'],
            PlatformAuditReasonCategory::PLATFORM_ADMINISTRATION,
        );
    }

    public function test_staff_and_audit_pages_are_capability_bound_and_reauthorize(): void
    {
        [$support] = $this->platformUser(PlatformRole::SUPPORT);
        $this->actingAs($support)->withSession(['active_workspace_type' => 'central']);
        app(PlatformContext::class)->forgetResolved();
        $this->get('/central/platform-staff')->assertForbidden();
        $this->get('/central/platform-audit')->assertForbidden();

        [$admin, $membership] = $this->platformUser(PlatformRole::ADMINISTRATOR);
        $this->actingAs($admin)->withSession(['active_workspace_type' => 'central']);
        app(PlatformContext::class)->forgetResolved();
        Livewire::test(PlatformStaff::class)->assertSee('Platform Staff');
        Livewire::test(PlatformAudit::class)->assertSee('Platform authority evidence');
        $membership->forceFill(['status' => PlatformMembershipStatus::SUSPENDED, 'suspended_at' => now()])->save();
        app(PlatformContext::class)->forgetResolved();
        Livewire::test(PlatformStaff::class)->assertForbidden();
    }

    public function test_central_shell_has_plane_identity_locale_search_and_no_website_action_or_customer_queries(): void
    {
        [$user] = $this->platformUser(PlatformRole::ADMINISTRATOR);
        $this->actingAs($user)->withSession(['active_workspace_type' => 'central']);
        app(PlatformContext::class)->forgetResolved();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        app(GlobalSearchService::class)->search(WorkspaceType::Central, 'private');
        $this->assertFalse(collect($queries)->contains(fn (string $sql) => str_contains($sql, 'prayer_requests') || str_contains($sql, 'content_items') || str_contains($sql, 'congregation_members') || str_contains($sql, 'media_assets')));

        Livewire::test(KeryonWorkspaceHeader::class)
            ->assertSee('Keryon Central')
            ->assertSee('Search Keryon')
            ->assertSee('English')
            ->assertDontSee('Go to Website');
        Livewire::test(CentralHome::class)->assertSee('Platform Operations')->assertSee('Production access remains unavailable');
    }

    public function test_production_environment_fails_closed_until_mfa_exists(): void
    {
        $original = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        try {
            app(RequireCentralMfaReadiness::class)->handle(Request::create('/central'), fn () => response('unsafe'));
            $this->fail('Production Central should fail closed.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        } finally {
            app()->detectEnvironment(fn () => $original);
        }
    }

    public function test_bootstrap_command_rejects_invalid_identity_role_and_duplicate(): void
    {
        $this->artisan('platform:grant-access', ['email' => 'missing@example.test', 'role' => 'administrator', '--origin' => 'test', '--operator' => 'test-suite'])->assertFailed();
        $unverified = User::factory()->unverified()->create();
        $this->artisan('platform:grant-access', ['email' => $unverified->email, 'role' => 'administrator', '--origin' => 'test', '--operator' => 'test-suite'])->assertFailed();
        $user = User::factory()->create();
        $this->artisan('platform:grant-access', ['email' => $user->email, 'role' => 'not-a-role', '--origin' => 'test', '--operator' => 'test-suite'])->assertFailed();
        $this->artisan('platform:grant-access', ['email' => $user->email, 'role' => 'administrator', '--origin' => 'test', '--operator' => 'test-suite'])->assertSuccessful();
        $this->artisan('platform:grant-access', ['email' => $user->email, 'role' => 'administrator', '--origin' => 'test', '--operator' => 'test-suite'])->assertFailed();
        $this->assertSame(1, PlatformMembership::count());
        $this->assertSame(1, PlatformAuditEvent::count());
    }

    /** @return array{User,PlatformMembership} */
    private function platformUser(PlatformRole $role): array
    {
        $user = User::factory()->create();
        $membership = app(PlatformStaffService::class)->create($user, $role, null, PlatformAuditReasonCategory::PLATFORM_ADMINISTRATION, 'Test fixture');

        return [$user, $membership];
    }
}
