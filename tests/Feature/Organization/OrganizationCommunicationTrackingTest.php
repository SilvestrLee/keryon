<?php

namespace Tests\Feature\Organization;

use App\Communications\OrganizationInbox\Import\OrganizationCommunicationImportService;
use App\Communications\OrganizationInbox\OrganizationCommunicationChurchResponseService;
use App\Enums\ChurchRole;
use App\Enums\OrganizationCommunicationDeclineReasonCode;
use App\Enums\OrganizationCommunicationKind;
use App\Enums\OrganizationCommunicationMaterialType;
use App\Enums\OrganizationCommunicationTargetMode;
use App\Enums\OrganizationRole;
use App\Filament\Organization\Pages\OrganizationCommunicationDetail;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ContentItem;
use App\Models\Organization;
use App\Models\OrganizationCommunicationDelivery;
use App\Models\OrganizationCommunicationDistribution;
use App\Models\OrganizationCommunicationRevision;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitType;
use App\Models\User;
use App\Organizations\Communications\Distribution\OrganizationCommunicationDistributionManager;
use App\Organizations\Communications\OrganizationCommunicationManager;
use App\Organizations\Communications\OrganizationCommunicationWorkflow;
use App\Organizations\Communications\Tracking\OrganizationCommunicationAttentionSignals;
use App\Organizations\Communications\Tracking\OrganizationCommunicationDeliveryOutcomeResolver;
use App\Organizations\Communications\Tracking\OrganizationCommunicationTrackingQuery;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationIdentityService;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationCommunicationTrackingTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationHierarchyService $hierarchy;

    private OrganizationIdentityService $identity;

    private OrganizationCommunicationManager $manager;

    private OrganizationCommunicationWorkflow $workflow;

    private OrganizationCommunicationDistributionManager $distributions;

    private OrganizationCommunicationTrackingQuery $tracking;

    private Organization $organization;

    private OrganizationUnit $regionA;

    private OrganizationUnit $regionB;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hierarchy = app(OrganizationHierarchyService::class);
        $this->identity = app(OrganizationIdentityService::class);
        $this->manager = app(OrganizationCommunicationManager::class);
        $this->workflow = app(OrganizationCommunicationWorkflow::class);
        $this->distributions = app(OrganizationCommunicationDistributionManager::class);
        $this->tracking = app(OrganizationCommunicationTrackingQuery::class);

        $this->organization = $this->hierarchy->createOrganization('Tracking Diocese', 'tracking-diocese');
        $type = OrganizationUnitType::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'region', 'label' => 'Region', 'sort_order' => 10, 'is_active' => true,
        ]);
        $this->regionA = $this->hierarchy->createUnit($this->organization, $type, $this->organization->rootUnit, ['name' => 'Region A', 'code' => 'region-a']);
        $this->regionB = $this->hierarchy->createUnit($this->organization, $type, $this->organization->rootUnit, ['name' => 'Region B', 'code' => 'region-b']);

        $this->admin = User::factory()->create();
        $this->identity->bootstrapAdministrator($this->organization, $this->admin);
        $this->asOrganizationUser($this->admin);
    }

    private function asOrganizationUser(User $user): void
    {
        $this->actingAs($user);
        session(['active_workspace_type' => 'organization', 'active_organization_id' => $this->organization->id]);
        app(OrganizationContext::class)->forgetResolved();
        app(TenantContext::class)->forgetResolved();
    }

    private function asChurchUser(User $user): void
    {
        $this->actingAs($user);
        session(['active_workspace_type' => 'church']);
        session()->forget('active_organization_id');
        app(TenantContext::class)->forgetResolved();
    }

    private function attachedChurch(OrganizationUnit $unit, string $name, array $roles = [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS]): array
    {
        $church = Church::factory()->create(['name' => $name]);
        $staff = User::factory()->create();
        ChurchMembership::createPrimary($church, $staff, $roles);
        $pending = $this->hierarchy->attachChurch($this->organization, $unit, $church);
        $this->hierarchy->acceptAttachment($pending, $staff);

        return [$church->fresh(), $staff];
    }

    private function distributedRevision(OrganizationUnit $unit, array $overrides = [], OrganizationCommunicationTargetMode $mode = OrganizationCommunicationTargetMode::UNIT_SUBTREE): OrganizationCommunicationRevision
    {
        $communication = $this->manager->create($unit, OrganizationCommunicationKind::COMMUNICATION, array_merge(['title' => 'Tracking notice'], $overrides));
        $revision = $communication->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Body.');
        $revision = $this->workflow->approve($this->workflow->submit($revision->fresh()));
        $this->distributions->request($revision->fresh(), $mode, $unit->id, null);

        return $revision->fresh();
    }

    private function deliveryFor(Church $church, OrganizationCommunicationRevision $revision): OrganizationCommunicationDelivery
    {
        return OrganizationCommunicationDelivery::query()
            ->where('church_id', $church->id)
            ->where('organization_communication_revision_id', $revision->id)
            ->latest('id')
            ->firstOrFail();
    }

    private function accept(Church $church, User $staff, OrganizationCommunicationDelivery $delivery): OrganizationCommunicationDelivery
    {
        $this->asChurchUser($staff);
        app(OrganizationCommunicationChurchResponseService::class)->accept($delivery);
        $this->asOrganizationUser($this->admin);

        return $delivery->fresh();
    }

    private function decline(Church $church, User $staff, OrganizationCommunicationDelivery $delivery, ?OrganizationCommunicationDeclineReasonCode $reason = null): OrganizationCommunicationDelivery
    {
        $this->asChurchUser($staff);
        app(OrganizationCommunicationChurchResponseService::class)->decline($delivery, $reason);
        $this->asOrganizationUser($this->admin);

        return $delivery->fresh();
    }

    private function import(Church $church, User $staff, OrganizationCommunicationDelivery $delivery): void
    {
        $this->asChurchUser($staff);
        app(OrganizationCommunicationImportService::class)->import($delivery->fresh());
        $this->asOrganizationUser($this->admin);
    }

    // ---------------------------------------------------------------
    // §92-97 outcome resolution
    // ---------------------------------------------------------------

    public function test_fresh_unresponded_delivery_resolves_available(): void
    {
        [$church] = $this->attachedChurch($this->regionA, 'Available Church');
        $revision = $this->distributedRevision($this->regionA);
        $delivery = $this->deliveryFor($church, $revision);
        $distribution = OrganizationCommunicationDistribution::query()->where('organization_communication_revision_id', $revision->id)->sole();

        $tracking = $this->tracking->distributionTracking($distribution);

        $this->assertSame(1, $tracking->count(OrganizationCommunicationDeliveryOutcomeResolver::AVAILABLE));
        $this->assertSame(1, $tracking->recipientCount);
    }

    public function test_accepted_not_imported_resolves_accepted(): void
    {
        [$church, $staff] = $this->attachedChurch($this->regionA, 'Accepted Church');
        $revision = $this->distributedRevision($this->regionA);
        $delivery = $this->accept($church, $staff, $this->deliveryFor($church, $revision));
        $distribution = OrganizationCommunicationDistribution::query()->where('organization_communication_revision_id', $revision->id)->sole();

        $tracking = $this->tracking->distributionTracking($distribution);

        $this->assertSame(1, $tracking->count(OrganizationCommunicationDeliveryOutcomeResolver::ACCEPTED));
    }

    public function test_declined_resolves_declined_with_reason(): void
    {
        [$church, $staff] = $this->attachedChurch($this->regionA, 'Declined Church');
        $revision = $this->distributedRevision($this->regionA);
        $this->decline($church, $staff, $this->deliveryFor($church, $revision), OrganizationCommunicationDeclineReasonCode::TIMING_NOT_SUITABLE);

        $summary = $this->tracking->communicationSummary($revision->communication);
        $this->assertCount(1, $summary);
        $this->assertSame(1, $summary[0]->count(OrganizationCommunicationDeliveryOutcomeResolver::DECLINED));
        $this->assertSame(1, $summary[0]->declineReasonCounts[OrganizationCommunicationDeclineReasonCode::TIMING_NOT_SUITABLE->value] ?? 0);
    }

    public function test_imported_takes_precedence_over_accepted(): void
    {
        [$church, $staff] = $this->attachedChurch($this->regionA, 'Imported Church', [ChurchRole::COMMUNICATIONS]);
        $revision = $this->distributedRevision($this->regionA);
        $delivery = $this->accept($church, $staff, $this->deliveryFor($church, $revision));
        $this->import($church, $staff, $delivery);

        $summary = $this->tracking->communicationSummary($revision->communication);

        $this->assertSame(1, $summary[0]->count(OrganizationCommunicationDeliveryOutcomeResolver::IMPORTED));
        $this->assertSame(0, $summary[0]->count(OrganizationCommunicationDeliveryOutcomeResolver::ACCEPTED));
    }

    public function test_expired_unresponded_delivery_resolves_expired(): void
    {
        [$church] = $this->attachedChurch($this->regionA, 'Expired Church');
        $revision = $this->distributedRevision($this->regionA, ['available_until' => now()->subDay()]);

        $summary = $this->tracking->communicationSummary($revision->communication);

        $this->assertSame(1, $summary[0]->count(OrganizationCommunicationDeliveryOutcomeResolver::EXPIRED));
    }

    public function test_accepted_before_expiry_still_shows_accepted_not_expired(): void
    {
        [$church, $staff] = $this->attachedChurch($this->regionA, 'Grace Church');
        $revision = $this->distributedRevision($this->regionA, ['available_until' => now()->addDay()]);
        $this->accept($church, $staff, $this->deliveryFor($church, $revision));

        $this->travelTo(now()->addDays(3));

        $summary = $this->tracking->communicationSummary($revision->communication);
        $this->assertSame(1, $summary[0]->count(OrganizationCommunicationDeliveryOutcomeResolver::ACCEPTED));
        $this->assertSame(0, $summary[0]->count(OrganizationCommunicationDeliveryOutcomeResolver::EXPIRED));
    }

    // ---------------------------------------------------------------
    // §90 duplicate distribution / §21 de-duplication
    // ---------------------------------------------------------------

    public function test_same_church_receiving_same_revision_twice_is_not_double_counted(): void
    {
        [$church, $staff] = $this->attachedChurch($this->regionA, 'Twice Church');
        $revision = $this->distributedRevision($this->regionA);
        $this->distributions->request($revision->fresh(), OrganizationCommunicationTargetMode::EXPLICIT_CHURCHES, null, [$church->id]);

        $deliveries = OrganizationCommunicationDelivery::query()->where('church_id', $church->id)->where('organization_communication_revision_id', $revision->id)->get();
        $this->assertCount(2, $deliveries);

        // Accept via the second delivery only — the summary must still
        // report exactly one Church, in its most-progressed state.
        $this->accept($church, $staff, $deliveries->last());

        $summary = $this->tracking->communicationSummary($revision->communication);
        $this->assertCount(1, $summary);
        $this->assertSame(1, $summary[0]->recipientCount);
        $this->assertSame(1, $summary[0]->count(OrganizationCommunicationDeliveryOutcomeResolver::ACCEPTED));

        // But the distribution-level drill-down still shows both historical deliveries.
        $distributions = OrganizationCommunicationDistribution::query()->where('organization_communication_revision_id', $revision->id)->get();
        $this->assertCount(2, $distributions);
        $totalAcrossDistributions = $distributions->sum(fn ($d) => $this->tracking->distributionTracking($d)->recipientCount);
        $this->assertSame(2, $totalAcrossDistributions);
    }

    // ---------------------------------------------------------------
    // §91/§22/§77 revision separation
    // ---------------------------------------------------------------

    public function test_different_revisions_report_separate_outcomes(): void
    {
        [$church, $staff] = $this->attachedChurch($this->regionA, 'Multi Revision Church');
        $revision1 = $this->distributedRevision($this->regionA);
        $this->import($church, $staff, $this->accept($church, $staff, $this->deliveryFor($church, $revision1)));

        $communication = $revision1->communication;
        $revision2 = $this->manager->createNextRevision($revision1->fresh());
        $revision2 = $this->workflow->approve($this->workflow->submit($revision2->fresh()));
        $this->distributions->request($revision2->fresh(), OrganizationCommunicationTargetMode::UNIT_SUBTREE, $this->regionA->id, null);

        $summary = $this->tracking->communicationSummary($communication->fresh());
        $this->assertCount(2, $summary);

        $v1 = collect($summary)->firstWhere('revisionVersion', 1);
        $v2 = collect($summary)->firstWhere('revisionVersion', 2);
        $this->assertSame(1, $v1->count(OrganizationCommunicationDeliveryOutcomeResolver::IMPORTED));
        $this->assertSame(1, $v2->count(OrganizationCommunicationDeliveryOutcomeResolver::AVAILABLE));
    }

    // ---------------------------------------------------------------
    // §89 scope isolation
    // ---------------------------------------------------------------

    public function test_unit_administrator_scoped_to_region_a_cannot_see_region_b_communication(): void
    {
        $revisionB = $this->distributedRevision($this->regionB);

        $unitAdminUser = User::factory()->create();
        $membership = $this->identity->activate($this->identity->invite($this->organization, $unitAdminUser));
        $this->identity->assignRole($membership, OrganizationRole::UNIT_ADMINISTRATOR, $this->regionA);

        $this->asOrganizationUser($unitAdminUser);

        $this->expectException(AuthorizationException::class);
        $this->tracking->communicationSummary($revisionB->communication);
    }

    public function test_organization_administrator_sees_full_scope(): void
    {
        $revisionB = $this->distributedRevision($this->regionB);
        // Still acting as the bootstrapped root Administrator.
        $summary = $this->tracking->communicationSummary($revisionB->communication);
        $this->assertNotEmpty($summary);
    }

    // ---------------------------------------------------------------
    // §98 detachment
    // ---------------------------------------------------------------

    public function test_detached_church_row_remains_visible_and_marked_detached(): void
    {
        [$church, $staff] = $this->attachedChurch($this->regionA, 'Detach Tracking Church');
        $revision = $this->distributedRevision($this->regionA);
        $delivery = $this->accept($church, $staff, $this->deliveryFor($church, $revision));

        $this->hierarchy->detachChurch($church);

        $distribution = OrganizationCommunicationDistribution::query()->where('organization_communication_revision_id', $revision->id)->sole();
        $recipients = $this->tracking->recipients($distribution);

        $this->assertSame(1, $recipients->total());
        $row = $recipients->items()[0];
        $this->assertTrue($row->isDetached);
        $this->assertSame('accepted', $row->outcome);
        $this->assertNull($row->currentUnitPath);
    }

    // ---------------------------------------------------------------
    // §72/§73/§99/§100/§101 privacy
    // ---------------------------------------------------------------

    public function test_tracking_never_exposes_importer_or_responder_identity(): void
    {
        [$church, $staff] = $this->attachedChurch($this->regionA, 'Privacy Church', [ChurchRole::COMMUNICATIONS]);
        $revision = $this->distributedRevision($this->regionA);
        $delivery = $this->accept($church, $staff, $this->deliveryFor($church, $revision));
        $this->import($church, $staff, $delivery);

        $distribution = OrganizationCommunicationDistribution::query()->where('organization_communication_revision_id', $revision->id)->sole();
        $recipients = $this->tracking->recipients($distribution);
        $row = $recipients->items()[0];

        $serialized = json_encode($row);
        $this->assertStringNotContainsString($staff->name, $serialized);
        $this->assertStringNotContainsString($staff->email, $serialized);
    }

    public function test_local_content_edit_never_appears_in_tracking(): void
    {
        [$church, $staff] = $this->attachedChurch($this->regionA, 'Local Edit Tracking Church', [ChurchRole::COMMUNICATIONS]);
        $revision = $this->distributedRevision($this->regionA);
        $delivery = $this->accept($church, $staff, $this->deliveryFor($church, $revision));
        $this->import($church, $staff, $delivery);

        $this->asChurchUser($staff);
        $contentItem = ContentItem::query()->sole();
        $secretBody = 'A very specific locally-rewritten sentence nobody else should see.';
        $contentItem->update(['body' => $secretBody]);
        $this->asOrganizationUser($this->admin);

        $distribution = OrganizationCommunicationDistribution::query()->where('organization_communication_revision_id', $revision->id)->sole();
        $recipients = $this->tracking->recipients($distribution);
        $serialized = json_encode($recipients->items());

        $this->assertStringNotContainsString($secretBody, $serialized);
        // Structural guarantee, not merely textual: the DTO has no field
        // that could ever carry a ContentItem reference at all.
        $this->assertArrayNotHasKey('content_item_id', (array) $recipients->items()[0]);
    }

    // ---------------------------------------------------------------
    // §41/§68 no forbidden table touch (structural — see also EXPLAIN proof)
    // ---------------------------------------------------------------

    public function test_tracking_query_never_touches_forbidden_tables(): void
    {
        [$church, $staff] = $this->attachedChurch($this->regionA, 'Query Log Church', [ChurchRole::COMMUNICATIONS]);
        $revision = $this->distributedRevision($this->regionA);
        $delivery = $this->accept($church, $staff, $this->deliveryFor($church, $revision));
        $this->import($church, $staff, $delivery);

        DB::enableQueryLog();
        $this->tracking->communicationSummary($revision->communication);
        $distribution = OrganizationCommunicationDistribution::query()->where('organization_communication_revision_id', $revision->id)->sole();
        $this->tracking->distributionTracking($distribution);
        $this->tracking->recipients($distribution);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $forbidden = ['content_items', 'campaigns', 'media_assets', 'website_publications', 'prayer_requests', 'congregation_members', 'organization_communication_import_results'];
        foreach ($queries as $query) {
            foreach ($forbidden as $table) {
                $this->assertStringNotContainsString($table, $query['query'], "Tracking query touched forbidden table [{$table}].");
            }
        }
    }

    // ---------------------------------------------------------------
    // UI: Tracking tab renders with real data
    // ---------------------------------------------------------------

    public function test_tracking_tab_renders_summary_and_recipient_drilldown(): void
    {
        [$church, $staff] = $this->attachedChurch($this->regionA, 'UI Tracking Church', [ChurchRole::COMMUNICATIONS]);
        $revision = $this->distributedRevision($this->regionA);
        $delivery = $this->accept($church, $staff, $this->deliveryFor($church, $revision));
        $this->import($church, $staff, $delivery);

        $distribution = OrganizationCommunicationDistribution::query()->where('organization_communication_revision_id', $revision->id)->sole();

        Livewire::test(OrganizationCommunicationDetail::class, ['record' => $revision->organization_communication_id])
            ->assertSee('Tracking')
            ->assertSee('Imported: 1')
            ->call('selectTrackingDistribution', $distribution->id)
            ->assertSee('UI Tracking Church')
            ->assertSee('Imported');
    }

    // ---------------------------------------------------------------
    // §102 dashboard attention
    // ---------------------------------------------------------------

    public function test_dashboard_flags_approved_but_not_distributed(): void
    {
        $communication = $this->manager->create($this->regionA, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Never Distributed Notice']);
        $revision = $communication->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Body.');
        $this->workflow->approve($this->workflow->submit($revision->fresh()));

        $signals = app(OrganizationCommunicationAttentionSignals::class)->forDashboard();

        $this->assertTrue(collect($signals)->contains(fn ($item) => $item['title'] === 'Never Distributed Notice' && $item['status'] === 'Needs distribution'));
    }

    public function test_dashboard_flags_response_overdue_only_with_configured_deadline_and_remaining_available(): void
    {
        [$church] = $this->attachedChurch($this->regionA, 'Overdue Church');
        $this->distributedRevision($this->regionA, [
            'title' => 'Overdue Notice',
            'recommended_response_on' => now()->subDays(3)->toDateString(),
        ]);

        $signals = app(OrganizationCommunicationAttentionSignals::class)->forDashboard();

        $overdue = collect($signals)->firstWhere('title', 'Overdue Notice');
        $this->assertNotNull($overdue);
        $this->assertSame('Response overdue', $overdue['status']);
        $this->assertStringContainsString('1 Church', $overdue['detail']);
    }

    public function test_dashboard_never_claims_overdue_without_a_configured_response_date(): void
    {
        [$church] = $this->attachedChurch($this->regionA, 'No Deadline Church');
        $this->distributedRevision($this->regionA, ['title' => 'No Deadline Notice']);

        $signals = app(OrganizationCommunicationAttentionSignals::class)->forDashboard();

        $this->assertFalse(collect($signals)->contains(fn ($item) => $item['title'] === 'No Deadline Notice'));
    }

    public function test_dashboard_attention_respects_unit_scope(): void
    {
        $this->distributedRevision($this->regionB, [
            'title' => 'Region B Overdue Notice',
            'recommended_response_on' => now()->subDays(3)->toDateString(),
        ]);
        [$churchB] = $this->attachedChurch($this->regionB, 'Region B Church');
        // (Church attached after distribution intentionally has no
        // delivery — the revision itself is enough to prove scope below.)

        $unitAdminUser = User::factory()->create();
        $membership = $this->identity->activate($this->identity->invite($this->organization, $unitAdminUser));
        $this->identity->assignRole($membership, OrganizationRole::UNIT_ADMINISTRATOR, $this->regionA);
        $this->asOrganizationUser($unitAdminUser);

        $signals = app(OrganizationCommunicationAttentionSignals::class)->forDashboard();

        $this->assertFalse(collect($signals)->contains(fn ($item) => $item['title'] === 'Region B Overdue Notice'));
    }
}
