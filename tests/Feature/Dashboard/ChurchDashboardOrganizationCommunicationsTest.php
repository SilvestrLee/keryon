<?php

namespace Tests\Feature\Dashboard;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Communications\OrganizationInbox\ChurchOrganizationCommunicationQuery;
use App\Communications\OrganizationInbox\Import\OrganizationCommunicationImportService;
use App\Communications\OrganizationInbox\OrganizationCommunicationChurchResponseService;
use App\Dashboard\ChurchDashboardSnapshotBuilder;
use App\Enums\ChurchRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationCommunicationAdaptationPolicy;
use App\Enums\OrganizationCommunicationDeclineReasonCode;
use App\Enums\OrganizationCommunicationKind;
use App\Enums\OrganizationCommunicationMaterialType;
use App\Enums\OrganizationCommunicationTargetMode;
use App\Filament\Pages\ChurchDashboard;
use App\Filament\Pages\OrganizationInbox as OrganizationInboxPage;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Organization;
use App\Models\OrganizationCommunicationDelivery;
use App\Models\OrganizationCommunicationRevision;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitType;
use App\Models\User;
use App\Organizations\Communications\Distribution\OrganizationCommunicationDistributionManager;
use App\Organizations\Communications\OrganizationCommunicationManager;
use App\Organizations\Communications\OrganizationCommunicationWorkflow;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationIdentityService;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * K-ORG-COMMS-001G §30 — the Church Dashboard Organization Communications
 * awareness test matrix. Every test proves the Dashboard's single new
 * `DashboardAction` reuses `ChurchOrganizationCommunicationQuery`'s
 * existing `STATE_AVAILABLE` definition unchanged — no second precedence
 * rule is introduced here.
 */
class ChurchDashboardOrganizationCommunicationsTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationHierarchyService $hierarchy;

    private OrganizationIdentityService $identity;

    private OrganizationCommunicationManager $manager;

    private OrganizationCommunicationWorkflow $workflow;

    private OrganizationCommunicationDistributionManager $distributions;

    private Organization $organization;

    private OrganizationUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hierarchy = app(OrganizationHierarchyService::class);
        $this->identity = app(OrganizationIdentityService::class);
        $this->manager = app(OrganizationCommunicationManager::class);
        $this->workflow = app(OrganizationCommunicationWorkflow::class);
        $this->distributions = app(OrganizationCommunicationDistributionManager::class);

        $this->organization = $this->hierarchy->createOrganization('Diocese Network', 'diocese-network-dashboard');
        $type = OrganizationUnitType::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'region', 'label' => 'Region', 'sort_order' => 10, 'is_active' => true,
        ]);
        $this->unit = $this->hierarchy->createUnit($this->organization, $type, $this->organization->rootUnit, ['name' => 'Region A', 'code' => 'region-a']);

        $admin = User::factory()->create();
        $this->identity->bootstrapAdministrator($this->organization, $admin);
        $this->actingAsOrganizationUser($admin);
    }

    // ---------------------------------------------------------------
    // fixtures / helpers — mirrors OrganizationInboxTest's established
    // setup exactly, so the fixture shape is not a new pattern.
    // ---------------------------------------------------------------

    private function actingAsOrganizationUser(User $user): void
    {
        $this->actingAs($user);
        session(['active_workspace_type' => 'organization', 'active_organization_id' => $this->organization->id]);
        app(OrganizationContext::class)->forgetResolved();
        app(TenantContext::class)->forgetResolved();
    }

    private function actingAsChurchUser(User $user): void
    {
        $this->actingAs($user);
        session(['active_workspace_type' => 'church']);
        session()->forget('active_organization_id');
        app(TenantContext::class)->forgetResolved();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->denyDashboardEntitlements();
    }

    private function denyDashboardEntitlements(): void
    {
        $resolver = Mockery::mock(EntitlementResolver::class);
        $resolver->shouldReceive('allows')->andReturnFalse()->byDefault();
        $this->app->instance(EntitlementResolver::class, $resolver);
    }

    /** @return array{Church, User} the Church and its Primary Administrator+Communications staff user */
    private function attachedChurch(string $name, array $roles = [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS]): array
    {
        $church = Church::factory()->create(['name' => $name]);
        $staff = User::factory()->create();
        ChurchMembership::createPrimary($church, $staff, $roles);
        $pending = $this->hierarchy->attachChurch($this->organization, $this->unit, $church);
        $this->hierarchy->acceptAttachment($pending, $staff);

        return [$church->fresh(), $staff];
    }

    /** Adds a second, non-Primary membership at an already-attached Church. */
    private function addMembership(Church $church, array $roles, MembershipStatus $status = MembershipStatus::ACTIVE): ChurchMembership
    {
        $user = User::factory()->create();
        $membership = $user->memberships()->create(['church_id' => $church->id, 'status' => $status->value, 'joined_at' => now()]);
        $membership->assignRoles($roles);

        return $membership;
    }

    private function distributedRevision(array $overrides = [], OrganizationCommunicationKind $kind = OrganizationCommunicationKind::COMMUNICATION, ?array $onlyToChurchIds = null): OrganizationCommunicationRevision
    {
        $communication = $this->manager->create($this->unit, $kind, array_merge(['title' => 'Dashboard awareness notice'], $overrides));
        $revision = $communication->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Please share this with your congregation.');
        $revision = $this->workflow->approve($this->workflow->submit($revision->fresh()));

        if ($onlyToChurchIds !== null) {
            $this->distributions->request($revision->fresh(), OrganizationCommunicationTargetMode::EXPLICIT_CHURCHES, null, $onlyToChurchIds);
        } else {
            $this->distributions->request($revision->fresh(), OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
        }

        return $revision->fresh();
    }

    private function deliveryFor(Church $church, OrganizationCommunicationRevision $revision): OrganizationCommunicationDelivery
    {
        return OrganizationCommunicationDelivery::query()
            ->where('church_id', $church->id)
            ->where('organization_communication_revision_id', $revision->id)
            ->firstOrFail();
    }

    private function snapshotActionKeys(): array
    {
        return array_column(app(ChurchDashboardSnapshotBuilder::class)->build()->toArray()['actions'], 'key');
    }

    // ---------------------------------------------------------------
    // §30.1-3 — basic count behaviour
    // ---------------------------------------------------------------

    public function test_one_available_delivery_shows_the_dashboard_action(): void
    {
        [$church, $staff] = $this->attachedChurch('One Available Church');
        $this->distributedRevision();

        $this->actingAsChurchUser($staff);
        $snapshot = app(ChurchDashboardSnapshotBuilder::class)->build();
        $action = collect($snapshot->actions)->firstWhere('key', 'organization.communications.available');

        $this->assertNotNull($action);
        $this->assertSame(1, $action->count);
        $this->assertSame('Organization communications waiting', $action->title);
        $this->assertSame('1 communication shared with your Church is waiting for a response.', $action->description);
        $this->assertSame('View inbox', $action->label);
    }

    public function test_multiple_available_deliveries_show_the_correct_count(): void
    {
        [$church, $staff] = $this->attachedChurch('Multiple Available Church');
        $this->distributedRevision(['title' => 'First notice']);
        $this->distributedRevision(['title' => 'Second notice']);
        $this->distributedRevision(['title' => 'Third notice']);

        $this->actingAsChurchUser($staff);
        $snapshot = app(ChurchDashboardSnapshotBuilder::class)->build();
        $action = collect($snapshot->actions)->firstWhere('key', 'organization.communications.available');

        $this->assertSame(3, $action->count);
        $this->assertSame('3 communications shared with your Church are waiting for a response.', $action->description);
    }

    public function test_zero_available_deliveries_show_no_action(): void
    {
        [$church, $staff] = $this->attachedChurch('Zero Available Church');

        $this->actingAsChurchUser($staff);
        $this->assertNotContains('organization.communications.available', $this->snapshotActionKeys());
    }

    // ---------------------------------------------------------------
    // §30.4-8 — exact outcome exclusions
    // ---------------------------------------------------------------

    public function test_accepted_delivery_is_not_counted(): void
    {
        [$church, $staff] = $this->attachedChurch('Accepted Not Counted Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->actingAsChurchUser($staff);
        app(OrganizationCommunicationChurchResponseService::class)->accept($delivery);

        $this->assertNotContains('organization.communications.available', $this->snapshotActionKeys());
    }

    public function test_declined_delivery_is_not_counted(): void
    {
        [$church, $staff] = $this->attachedChurch('Declined Not Counted Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->actingAsChurchUser($staff);
        app(OrganizationCommunicationChurchResponseService::class)->decline($delivery, OrganizationCommunicationDeclineReasonCode::TIMING_NOT_SUITABLE);

        $this->assertNotContains('organization.communications.available', $this->snapshotActionKeys());
    }

    public function test_imported_delivery_is_not_counted(): void
    {
        [$church, $staff] = $this->attachedChurch('Imported Not Counted Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->actingAsChurchUser($staff);
        app(OrganizationCommunicationChurchResponseService::class)->accept($delivery);
        app(OrganizationCommunicationImportService::class)->import($delivery->fresh());

        $this->assertNotContains('organization.communications.available', $this->snapshotActionKeys());
    }

    public function test_expired_delivery_is_not_counted(): void
    {
        [$church, $staff] = $this->attachedChurch('Expired Not Counted Church');
        $revision = $this->distributedRevision(['available_until' => now()->subDay()]);
        $this->deliveryFor($church, $revision);

        $this->actingAsChurchUser($staff);
        $this->assertNotContains('organization.communications.available', $this->snapshotActionKeys());
    }

    public function test_withdrawn_delivery_is_not_counted(): void
    {
        // K-ORG-COMMS-001G §21/§24 — withdrawal is not yet implemented as
        // a product action; the delivery's own `state` column already
        // supports it, so this proves the count's exclusion rule directly
        // against a raw `state = withdrawn` row rather than waiting on a
        // future withdrawal feature.
        [$church, $staff] = $this->attachedChurch('Withdrawn Not Counted Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);
        DB::table('organization_communication_deliveries')->where('id', $delivery->id)->update(['state' => 'withdrawn']);

        $this->actingAsChurchUser($staff);
        $this->assertNotContains('organization.communications.available', $this->snapshotActionKeys());
    }

    // ---------------------------------------------------------------
    // §30.9-10 — no special-casing by delivery kind
    // ---------------------------------------------------------------

    public function test_reference_only_delivery_is_counted_while_available(): void
    {
        [$church, $staff] = $this->attachedChurch('Reference Only Church');
        $this->distributedRevision(['adaptation_policy' => OrganizationCommunicationAdaptationPolicy::REFERENCE_ONLY]);

        $this->actingAsChurchUser($staff);
        $this->assertContains('organization.communications.available', $this->snapshotActionKeys());
    }

    public function test_shared_campaign_delivery_is_counted_while_available(): void
    {
        [$church, $staff] = $this->attachedChurch('Shared Campaign Church');
        $this->distributedRevision([], OrganizationCommunicationKind::CAMPAIGN);

        $this->actingAsChurchUser($staff);
        $this->assertContains('organization.communications.available', $this->snapshotActionKeys());
    }

    // ---------------------------------------------------------------
    // §30.11 — cross-Church isolation
    // ---------------------------------------------------------------

    public function test_church_a_cannot_count_church_bs_deliveries(): void
    {
        [$churchA, $staffA] = $this->attachedChurch('Isolation Church A');
        [$churchB, $staffB] = $this->attachedChurch('Isolation Church B');
        $revision = $this->distributedRevision();
        $deliveryA = $this->deliveryFor($churchA, $revision);

        $this->actingAsChurchUser($staffB);
        app(OrganizationCommunicationChurchResponseService::class)->accept($this->deliveryFor($churchB, $revision));

        $this->assertSame(0, app(ChurchOrganizationCommunicationQuery::class)->availableCount());

        $this->actingAsChurchUser($staffA);
        $this->assertSame(1, app(ChurchOrganizationCommunicationQuery::class)->availableCount());
        $this->assertNotNull($deliveryA);
    }

    // ---------------------------------------------------------------
    // §30.12-16 — capability and membership gating
    // ---------------------------------------------------------------

    public function test_communications_role_sees_the_action(): void
    {
        [$church, $staff] = $this->attachedChurch('Communications Role Church', [ChurchRole::COMMUNICATIONS]);
        $this->distributedRevision();

        $this->actingAsChurchUser($staff);
        $this->assertContains('organization.communications.available', $this->snapshotActionKeys());
    }

    public function test_administrator_sees_the_action(): void
    {
        [$church, $staff] = $this->attachedChurch('Administrator Church', [ChurchRole::ADMINISTRATOR]);
        $this->distributedRevision();

        $this->actingAsChurchUser($staff);
        $this->assertContains('organization.communications.available', $this->snapshotActionKeys());
    }

    public function test_care_only_user_does_not_see_the_action(): void
    {
        [$church, $primaryStaff] = $this->attachedChurch('Care Only Church');
        $this->distributedRevision();
        $careMembership = $this->addMembership($church, [ChurchRole::CARE]);

        $this->actingAsChurchUser($careMembership->user);
        $this->assertNotContains('organization.communications.available', $this->snapshotActionKeys());
    }

    public function test_suspended_membership_does_not_see_the_action(): void
    {
        [$church, $primaryStaff] = $this->attachedChurch('Suspended Membership Church');
        $this->distributedRevision();
        $membership = $this->addMembership($church, [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS]);
        $membership->suspend();

        $this->actingAsChurchUser($membership->user);
        $this->assertNull(app(TenantContext::class)->currentMembership());
    }

    public function test_removed_membership_does_not_see_the_action(): void
    {
        [$church, $primaryStaff] = $this->attachedChurch('Removed Membership Church');
        $this->distributedRevision();
        $membership = $this->addMembership($church, [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS]);
        $membership->remove();

        $this->actingAsChurchUser($membership->user);
        $this->assertNull(app(TenantContext::class)->currentMembership());
    }

    public function test_direct_inbox_route_policy_remains_authoritative_alongside_the_dashboard_action(): void
    {
        [$church, $staff] = $this->attachedChurch('Inbox Policy Church', [ChurchRole::COMMUNICATIONS]);
        $this->distributedRevision();
        $careMembership = $this->addMembership($church, [ChurchRole::CARE]);

        $this->actingAsChurchUser($staff);
        $this->assertTrue(OrganizationInboxPage::canAccess());

        $this->actingAsChurchUser($careMembership->user);
        $this->assertFalse(OrganizationInboxPage::canAccess());
        $this->assertNotContains('organization.communications.available', $this->snapshotActionKeys());
    }

    // ---------------------------------------------------------------
    // browser-adjacent Livewire proof — CTA renders and links to Inbox
    // ---------------------------------------------------------------

    public function test_dashboard_renders_the_action_with_a_working_inbox_link(): void
    {
        [$church, $staff] = $this->attachedChurch('Rendered Action Church');
        $this->distributedRevision();

        $this->actingAsChurchUser($staff);
        Livewire::test(ChurchDashboard::class)
            ->assertSee('Organization communications waiting')
            ->assertSee('1 communication shared with your Church is waiting for a response.')
            ->assertSee('View inbox')
            ->assertSeeHtml('href="'.OrganizationInboxPage::getUrl().'"');
    }

    // ---------------------------------------------------------------
    // §31 — privacy-query proof: the new count must never touch local
    // Church records or any Organization material/asset/import table.
    // ---------------------------------------------------------------

    public function test_available_count_query_never_touches_forbidden_tables(): void
    {
        [$church, $staff] = $this->attachedChurch('Privacy Scan Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);
        // Both revisions are created while the Organization workspace is
        // still active (the required context for distribution) — the
        // switch into the Church workspace happens only once, right
        // before the response/import/count calls below.
        $this->distributedRevision(['title' => 'Second still-available notice']);

        $this->actingAsChurchUser($staff);
        app(OrganizationCommunicationChurchResponseService::class)->accept($delivery);
        app(OrganizationCommunicationImportService::class)->import($delivery->fresh());

        $forbidden = ['content_items', 'campaigns', 'media_assets', 'organization_communication_import_results', 'organization_communication_materials', 'organization_communication_assets'];
        $touched = [];
        DB::listen(function ($query) use (&$touched, $forbidden): void {
            foreach ($forbidden as $table) {
                if (str_contains($query->sql, $table)) {
                    $touched[] = $table;
                }
            }
        });

        $count = app(ChurchOrganizationCommunicationQuery::class)->availableCount();

        DB::flushQueryLog();
        $this->assertSame(1, $count);
        $this->assertSame([], $touched, 'availableCount() must not query: '.implode(', ', $touched));
    }

    // ---------------------------------------------------------------
    // §32 — no N+1: the query count for building the whole Dashboard
    // snapshot must not scale with the number of Organization
    // deliveries a Church has.
    // ---------------------------------------------------------------

    public function test_dashboard_query_count_does_not_scale_with_delivery_volume(): void
    {
        [$churchZero, $staffZero] = $this->attachedChurch('Zero Delivery Query Count Church');

        // Both Churches and every revision are created up front, while
        // the Organization workspace is still the active context — the
        // switch into each Church's own workspace happens only once,
        // immediately before that Church's own snapshot is measured.
        [$churchMany, $staffMany] = $this->attachedChurch('Several Delivery Query Count Church');
        $this->distributedRevision(['title' => 'Notice A'], onlyToChurchIds: [$churchMany->id]);
        $this->distributedRevision(['title' => 'Notice B'], onlyToChurchIds: [$churchMany->id]);
        $this->distributedRevision(['title' => 'Notice C'], onlyToChurchIds: [$churchMany->id]);
        $this->distributedRevision(['title' => 'Notice D'], onlyToChurchIds: [$churchMany->id]);
        $this->distributedRevision(['title' => 'Notice E'], onlyToChurchIds: [$churchMany->id]);

        $this->actingAsChurchUser($staffZero);
        DB::enableQueryLog();
        app(ChurchDashboardSnapshotBuilder::class)->build();
        $zeroDeliveryQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::disableQueryLog();

        $this->actingAsChurchUser($staffMany);
        DB::enableQueryLog();
        app(ChurchDashboardSnapshotBuilder::class)->build();
        $manyDeliveryQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::disableQueryLog();

        $this->assertSame($zeroDeliveryQueries, $manyDeliveryQueries, 'Dashboard query count must not scale with the number of Organization deliveries (no N+1).');
    }
}
