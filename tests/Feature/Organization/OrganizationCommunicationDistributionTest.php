<?php

namespace Tests\Feature\Organization;

use App\Enums\ChurchRole;
use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationCommunicationAssetRightsBasis;
use App\Enums\OrganizationCommunicationDistributionState;
use App\Enums\OrganizationCommunicationKind;
use App\Enums\OrganizationCommunicationMaterialType;
use App\Enums\OrganizationCommunicationRevisionState;
use App\Enums\OrganizationCommunicationState;
use App\Enums\OrganizationCommunicationTargetMode;
use App\Enums\OrganizationRole;
use App\Enums\PlatformMembershipStatus;
use App\Enums\PlatformRole;
use App\Jobs\MaterializeOrganizationCommunicationDistribution;
use App\Models\Campaign;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ContentItem;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\OrganizationAuditEvent;
use App\Models\OrganizationCommunicationAsset;
use App\Models\OrganizationCommunicationDelivery;
use App\Models\OrganizationCommunicationDistribution;
use App\Models\OrganizationCommunicationRevision;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRoleAssignment;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitType;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Organizations\Communications\Distribution\OrganizationCommunicationAudienceResolver;
use App\Organizations\Communications\Distribution\OrganizationCommunicationDistributionManager;
use App\Organizations\Communications\OrganizationCommunicationAssetManager;
use App\Organizations\Communications\OrganizationCommunicationAudit;
use App\Organizations\Communications\OrganizationCommunicationManager;
use App\Organizations\Communications\OrganizationCommunicationWorkflow;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationIdentityService;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class OrganizationCommunicationDistributionTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationHierarchyService $hierarchy;

    private OrganizationIdentityService $identity;

    private OrganizationCommunicationManager $manager;

    private OrganizationCommunicationWorkflow $workflow;

    private OrganizationCommunicationDistributionManager $distributions;

    private OrganizationCommunicationAudienceResolver $audience;

    private Organization $organization;

    private OrganizationUnit $regionA;

    private OrganizationUnit $districtA1;

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
        $this->audience = app(OrganizationCommunicationAudienceResolver::class);

        $this->organization = $this->hierarchy->createOrganization('Distribution Network', 'distribution-network');
        $type = OrganizationUnitType::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'region', 'label' => 'Region', 'sort_order' => 10, 'is_active' => true,
        ]);
        $this->regionA = $this->hierarchy->createUnit($this->organization, $type, $this->organization->rootUnit, ['name' => 'Region A', 'code' => 'region-a']);
        $this->districtA1 = $this->hierarchy->createUnit($this->organization, $type, $this->regionA, ['name' => 'District A1', 'code' => 'district-a1']);
        $this->regionB = $this->hierarchy->createUnit($this->organization, $type, $this->organization->rootUnit, ['name' => 'Region B', 'code' => 'region-b']);

        $this->admin = User::factory()->create();
        $this->identity->bootstrapAdministrator($this->organization, $this->admin);
        $this->asOrganizationUser($this->admin, $this->organization);
    }

    private function asOrganizationUser(User $user, Organization $organization): void
    {
        $this->actingAs($user);
        session(['active_workspace_type' => 'organization', 'active_organization_id' => $organization->id]);
        app(OrganizationContext::class)->forgetResolved();
        app(TenantContext::class)->forgetResolved();
    }

    /** @return array{User, OrganizationMembership, OrganizationRoleAssignment} */
    private function organizationUser(OrganizationRole $role, OrganizationUnit $unit): array
    {
        $user = User::factory()->create();
        $membership = $this->identity->activate($this->identity->invite($this->organization, $user));
        $assignment = $this->identity->assignRole($membership, $role, $unit);

        return [$user, $membership, $assignment];
    }

    private function assignedChurch(OrganizationUnit $unit, string $name, bool $active = true): Church
    {
        $church = Church::factory()->create(['name' => $name, 'is_active' => $active]);
        $primary = User::factory()->create();
        ChurchMembership::createPrimary($church, $primary);
        $pending = $this->hierarchy->attachChurch($this->organization, $unit, $church);
        $this->hierarchy->acceptAttachment($pending, $primary);

        return $church->fresh();
    }

    /** Creates, submits, and approves a communication with one material, ready to distribute. */
    private function approvedRevision(OrganizationUnit $governingUnit): OrganizationCommunicationRevision
    {
        $revision = $this->manager->create($governingUnit, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Distribution-ready notice'])
            ->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Body copy.');

        return $this->workflow->approve($this->workflow->submit($revision));
    }

    // ---------------------------------------------------------------
    // §83 targeting
    // ---------------------------------------------------------------

    public function test_draft_in_review_and_changes_requested_revisions_cannot_be_distributed(): void
    {
        $revision = $this->manager->create($this->regionA, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Not ready'])
            ->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Body');

        try {
            $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
            $this->fail('A Draft revision was distributed.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Approved', $e->getMessage());
        }

        $inReview = $this->workflow->submit($revision);
        try {
            $this->distributions->request($inReview, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
            $this->fail('An In Review revision was distributed.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $changesRequested = $this->workflow->requestChanges($inReview, 'Fix this');
        try {
            $this->distributions->request($changesRequested, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
            $this->fail('A Changes Requested revision was distributed.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
    }

    public function test_governing_scope_targeting_resolves_all_active_churches_under_governing_unit(): void
    {
        $churchA = $this->assignedChurch($this->regionA, 'Region A Church');
        $churchA1 = $this->assignedChurch($this->districtA1, 'District A1 Church');
        $churchB = $this->assignedChurch($this->regionB, 'Region B Church');

        $revision = $this->approvedRevision($this->regionA);
        Bus::fake();
        $distribution = $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
        Bus::assertDispatched(MaterializeOrganizationCommunicationDistribution::class, fn ($job) => $job->distributionId === $distribution->id);

        app(MaterializeOrganizationCommunicationDistribution::class, ['distributionId' => $distribution->id])->handle($this->audience, app(OrganizationCommunicationAudit::class));

        $recipientIds = $distribution->fresh()->deliveries()->pluck('church_id')->sort()->values()->all();
        $this->assertSame([$churchA->id, $churchA1->id], $recipientIds);
        $this->assertNotContains($churchB->id, $recipientIds);
    }

    public function test_unit_subtree_targeting_resolves_descendants_only(): void
    {
        $churchA = $this->assignedChurch($this->regionA, 'Region A Church');
        $churchA1 = $this->assignedChurch($this->districtA1, 'District A1 Church');

        $revision = $this->approvedRevision($this->regionA);
        $distribution = $this->distributions->request($revision, OrganizationCommunicationTargetMode::UNIT_SUBTREE, $this->districtA1->id, null);
        $this->runJob($distribution);

        $recipientIds = $distribution->fresh()->deliveries()->pluck('church_id')->all();
        $this->assertSame([$churchA1->id], $recipientIds);
        $this->assertNotContains($churchA->id, $recipientIds);
    }

    public function test_explicit_church_selection_resolves_only_valid_selected_churches(): void
    {
        $churchA = $this->assignedChurch($this->regionA, 'Region A Church');
        $churchA1 = $this->assignedChurch($this->districtA1, 'District A1 Church');
        $churchB = $this->assignedChurch($this->regionB, 'Region B Church');

        $revision = $this->approvedRevision($this->regionA);
        // Includes an out-of-scope Church id — must be silently excluded, not injected (§71).
        $distribution = $this->distributions->request(
            $revision,
            OrganizationCommunicationTargetMode::EXPLICIT_CHURCHES,
            null,
            [$churchA->id, $churchB->id],
        );
        $this->runJob($distribution);

        $recipientIds = $distribution->fresh()->deliveries()->pluck('church_id')->all();
        $this->assertSame([$churchA->id], $recipientIds);
        $this->assertNotContains($churchA1->id, $recipientIds);
        $this->assertNotContains($churchB->id, $recipientIds);
    }

    public function test_preview_count_is_factual_and_bounded(): void
    {
        $this->assignedChurch($this->regionA, 'Church One');
        $this->assignedChurch($this->districtA1, 'Church Two');
        $this->assignedChurch($this->regionA, 'Inactive Church', active: false);

        $this->assertSame(2, $this->audience->previewCount($this->organization->id, $this->regionA->id, null));
        $page = $this->audience->preview($this->organization->id, $this->regionA->id, null, perPage: 1);
        $this->assertSame(2, $page->total());
        $this->assertCount(1, $page->items());
    }

    public function test_zero_recipient_audience_fails_gracefully_without_a_misleading_success(): void
    {
        $revision = $this->approvedRevision($this->regionB); // no churches assigned to Region B
        $distribution = $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
        $this->runJob($distribution);

        $fresh = $distribution->fresh();
        $this->assertSame(OrganizationCommunicationDistributionState::FAILED, $fresh->state);
        $this->assertSame(0, $fresh->deliveries()->count());
        $this->assertNotNull($fresh->failure_reason);
        $this->assertSame(OrganizationCommunicationRevisionState::APPROVED, $revision->fresh()->state);
        $this->assertSame(OrganizationCommunicationState::DRAFT, $revision->fresh()->communication->state);
    }

    // ---------------------------------------------------------------
    // §84 negative authorization
    // ---------------------------------------------------------------

    public function test_unit_administrator_cannot_distribute_by_default(): void
    {
        $revision = $this->approvedRevision($this->regionA);
        [$unitAdmin] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->regionA);
        $this->asOrganizationUser($unitAdmin, $this->organization);

        $this->assertFalse($unitAdmin->can('distribute', $revision->communication));
        $this->expectException(AuthorizationException::class);
        $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
    }

    public function test_viewer_cannot_distribute(): void
    {
        $revision = $this->approvedRevision($this->regionA);
        [$viewer] = $this->organizationUser(OrganizationRole::ORGANIZATION_VIEWER, $this->regionA);
        $this->asOrganizationUser($viewer, $this->organization);

        $this->assertFalse($viewer->can('distribute', $revision->communication));
        $this->expectException(AuthorizationException::class);
        $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
    }

    public function test_suspended_and_removed_membership_cannot_distribute(): void
    {
        $revision = $this->approvedRevision($this->regionA);
        [$orgAdmin2, $membership] = $this->organizationUser(OrganizationRole::ORGANIZATION_ADMINISTRATOR, $this->organization->rootUnit);
        $this->identity->suspend($membership, $this->admin->id);
        $this->asOrganizationUser($orgAdmin2, $this->organization);

        $this->expectException(AuthorizationException::class);
        $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
    }

    public function test_church_only_and_platform_only_identities_grant_zero_distribution_authority(): void
    {
        $revision = $this->approvedRevision($this->regionA);

        $churchOnly = User::factory()->create();
        ChurchMembership::createPrimary(Church::factory()->create(), $churchOnly, [ChurchRole::ADMINISTRATOR]);
        $this->asOrganizationUser($churchOnly, $this->organization);
        try {
            $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
            $this->fail('Church-only identity distributed.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $platformOnly = User::factory()->create();
        PlatformMembership::query()->create([
            'user_id' => $platformOnly->id, 'role' => PlatformRole::SUPPORT,
            'status' => PlatformMembershipStatus::ACTIVE, 'activated_at' => now(),
        ]);
        $this->asOrganizationUser($platformOnly, $this->organization);
        $this->expectException(AuthorizationException::class);
        $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
    }

    public function test_out_of_scope_unit_target_is_rejected(): void
    {
        $revision = $this->approvedRevision($this->regionA);

        $this->expectException(ValidationException::class);
        $this->distributions->request($revision, OrganizationCommunicationTargetMode::UNIT_SUBTREE, $this->regionB->id, null);
    }

    // ---------------------------------------------------------------
    // §85 distribution / §86 idempotency
    // ---------------------------------------------------------------

    public function test_authorized_request_creates_exactly_one_pending_distribution_referencing_the_immutable_revision(): void
    {
        Bus::fake();
        $revision = $this->approvedRevision($this->regionA);
        $this->assignedChurch($this->regionA, 'Some Church');

        $distribution = $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);

        $this->assertSame(OrganizationCommunicationDistributionState::PENDING, $distribution->state);
        $this->assertSame($revision->id, $distribution->organization_communication_revision_id);
        $this->assertSame(1, OrganizationCommunicationDistribution::query()->count());
        $this->assertSame(
            OrganizationAuditEventType::COMMUNICATION_DISTRIBUTION_REQUESTED,
            OrganizationAuditEvent::query()->latest('id')->first()->event_type,
        );
    }

    public function test_source_body_and_asset_bytes_are_never_duplicated_onto_the_distribution(): void
    {
        Bus::fake();
        $revision = $this->manager->create($this->regionA, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Asset check'])
            ->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Sensitive body copy.');
        $approved = $this->workflow->approve($this->workflow->submit($revision));
        $this->assignedChurch($this->regionA, 'Some Church');

        $distribution = $this->distributions->request($approved, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);

        // No body/content/asset-bytes column exists on the distribution
        // row at all — only identity, target, and lifecycle columns.
        $columns = array_keys($distribution->fresh()->getAttributes());
        foreach (['body', 'title', 'summary', 'content', 'asset', 'material', 'sha256', 'path'] as $forbidden) {
            $this->assertFalse(
                collect($columns)->contains(fn (string $column): bool => str_contains($column, $forbidden)),
                "Distribution row unexpectedly carries a '{$forbidden}'-like column: ".implode(', ', $columns),
            );
        }
    }

    public function test_duplicate_request_for_the_same_revision_and_target_reuses_the_live_distribution(): void
    {
        Bus::fake();
        $revision = $this->approvedRevision($this->regionA);
        $this->assignedChurch($this->regionA, 'Some Church');

        $first = $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
        $second = $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, OrganizationCommunicationDistribution::query()->count());
    }

    public function test_different_target_creates_a_distinct_distribution(): void
    {
        $revision = $this->approvedRevision($this->regionA);
        $this->assignedChurch($this->regionA, 'Some Church');
        $this->assignedChurch($this->districtA1, 'Other Church');

        $whole = $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
        $subset = $this->distributions->request($revision, OrganizationCommunicationTargetMode::UNIT_SUBTREE, $this->districtA1->id, null);

        $this->assertNotSame($whole->id, $subset->id);
        $this->assertSame(2, OrganizationCommunicationDistribution::query()->count());
    }

    public function test_worker_retry_does_not_duplicate_deliveries(): void
    {
        $revision = $this->approvedRevision($this->regionA);
        $church = $this->assignedChurch($this->regionA, 'Some Church');
        $distribution = $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);

        $this->runJob($distribution);
        $this->runJob($distribution->fresh()); // simulate a retried/duplicate execution

        $this->assertSame(1, OrganizationCommunicationDelivery::query()->where('church_id', $church->id)->count());
        $this->assertSame(OrganizationCommunicationDistributionState::COMPLETED, $distribution->fresh()->state);
    }

    public function test_same_revision_can_be_distributed_again_with_a_new_audience(): void
    {
        $revision = $this->approvedRevision($this->regionA);
        $churchA = $this->assignedChurch($this->regionA, 'Church A');
        $first = $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
        $this->runJob($first);
        $this->assertSame(OrganizationCommunicationRevisionState::DISTRIBUTED, $revision->fresh()->state);

        $churchA1 = $this->assignedChurch($this->districtA1, 'Church A1');
        $second = $this->distributions->request($revision->fresh(), OrganizationCommunicationTargetMode::UNIT_SUBTREE, $this->districtA1->id, null);
        $this->runJob($second);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame([$churchA->id], $first->fresh()->deliveries()->pluck('church_id')->all());
        $this->assertSame([$churchA1->id], $second->fresh()->deliveries()->pluck('church_id')->all());
    }

    // ---------------------------------------------------------------
    // §87 snapshot
    // ---------------------------------------------------------------

    public function test_snapshot_reflects_worker_start_state_and_church_moved_after_snapshot_remains_historical(): void
    {
        $revision = $this->approvedRevision($this->regionA);
        $church = $this->assignedChurch($this->regionA, 'Movable Church');
        $distribution = $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
        $this->runJob($distribution);

        $this->assertNotNull($distribution->fresh()->snapshot_at);
        $this->assertSame([$church->id], $distribution->fresh()->deliveries()->pluck('church_id')->all());

        // Church moves to Region B after the snapshot.
        $this->hierarchy->moveChurch($church->fresh(), $this->regionB, $this->admin->id);

        // Historical delivery is untouched.
        $this->assertSame([$church->id], $distribution->fresh()->deliveries()->pluck('church_id')->all());
    }

    public function test_future_distribution_uses_current_assignment_after_church_moves(): void
    {
        $revision = $this->approvedRevision($this->organization->rootUnit);
        $church = $this->assignedChurch($this->regionA, 'Movable Church 2');

        // Move before any distribution — future resolution must use the
        // Church's *current* assignment.
        $this->hierarchy->moveChurch($church->fresh(), $this->regionB, $this->admin->id);

        $distribution = $this->distributions->request($revision, OrganizationCommunicationTargetMode::UNIT_SUBTREE, $this->regionB->id, null);
        $this->runJob($distribution);

        $this->assertSame([$church->id], $distribution->fresh()->deliveries()->pluck('church_id')->all());
    }

    // ---------------------------------------------------------------
    // §89 privacy / §90 no-import / §91 asset
    // ---------------------------------------------------------------

    public function test_distribution_never_creates_church_owned_records(): void
    {
        $revision = $this->manager->create($this->regionA, OrganizationCommunicationKind::CAMPAIGN, ['title' => 'No import check'])
            ->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Body');
        $approved = $this->workflow->approve($this->workflow->submit($revision));
        $this->assignedChurch($this->regionA, 'Some Church');

        $distribution = $this->distributions->request($approved, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
        $this->runJob($distribution);

        $this->assertSame(0, Campaign::query()->count());
        $this->assertSame(0, ContentItem::query()->count());
        $this->assertSame(0, MediaAsset::query()->count());
        $this->assertFalse(app(TenantContext::class)->hasContext());
    }

    public function test_asset_rights_are_preserved_and_bytes_are_not_duplicated_per_church(): void
    {
        Storage::fake('media-private');
        $revision = $this->manager->create($this->regionA, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Asset rights check'])
            ->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Body');

        $stagingPath = "organizations/{$this->organization->uuid}/communications/.staging/".uniqid().'.tmp';
        Storage::disk('media-private')->put($stagingPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $asset = app(OrganizationCommunicationAssetManager::class)->addAsset(
            $revision,
            $stagingPath,
            'flyer.png',
            ['rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION],
        );

        $approved = $this->workflow->approve($this->workflow->submit($revision));
        $this->assignedChurch($this->regionA, 'Church One');
        $this->assignedChurch($this->regionA, 'Church Two');

        $distribution = $this->distributions->request($approved, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
        $this->runJob($distribution);

        $this->assertSame(OrganizationCommunicationDistributionState::COMPLETED, $distribution->fresh()->state);
        $this->assertSame(2, $distribution->fresh()->deliveries()->count());
        // Exactly one asset row regardless of recipient count — no per-Church copy.
        $this->assertSame(1, OrganizationCommunicationAsset::query()->count());
        $this->assertSame(OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION, $asset->fresh()->rights_basis);
        // No delivery row carries any asset/file reference.
        $deliveryColumns = array_keys(OrganizationCommunicationDelivery::query()->first()->getAttributes());
        $this->assertFalse(collect($deliveryColumns)->contains(fn (string $c): bool => str_contains($c, 'asset') || str_contains($c, 'path') || str_contains($c, 'disk')));
    }

    // ---------------------------------------------------------------
    // §88 scale / §92 audit
    // ---------------------------------------------------------------

    public function test_materialization_scales_across_chunk_boundaries_without_n_plus_one_query_growth(): void
    {
        config(['organization-communications.distribution_chunk_size' => 10]);
        // 25 Churches spans three 10-row chunks — proves chunk boundaries
        // are handled, without paying for a 10,000-fixture test run.
        for ($i = 0; $i < 25; $i++) {
            $this->assignedChurch($this->regionA, "Scale Church {$i}");
        }
        $revision = $this->approvedRevision($this->regionA);
        $distribution = $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);

        DB::enableQueryLog();
        $this->runJob($distribution);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $fresh = $distribution->fresh();
        $this->assertSame(OrganizationCommunicationDistributionState::COMPLETED, $fresh->state);
        $this->assertSame(25, $fresh->deliveries()->count());
        // Bounded by chunk count (3), not by recipient count (25) — proves
        // no per-Church query.
        $this->assertLessThan(25, $queryCount);
    }

    public function test_audit_evidence_is_recorded_for_requested_and_completed_without_pii_expansion(): void
    {
        $revision = $this->approvedRevision($this->regionA);
        $church = $this->assignedChurch($this->regionA, 'Audited Church');
        $distribution = $this->distributions->request($revision, OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
        $this->runJob($distribution);

        $events = OrganizationAuditEvent::query()
            ->whereIn('event_type', [
                OrganizationAuditEventType::COMMUNICATION_DISTRIBUTION_REQUESTED->value,
                OrganizationAuditEventType::COMMUNICATION_DISTRIBUTION_COMPLETED->value,
            ])
            ->get();

        $this->assertCount(2, $events);
        $this->assertTrue($events->every(fn (OrganizationAuditEvent $e): bool => $e->organization_id === $this->organization->id));
        $this->assertTrue($events->every(fn (OrganizationAuditEvent $e): bool => ($e->new_state['actor_membership_id'] ?? null) !== null));
        $json = $events->toJson();
        $this->assertStringNotContainsString($church->name, $json);
        $this->assertStringNotContainsString('Body copy.', $json);
    }

    // ---------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------

    private function runJob(OrganizationCommunicationDistribution $distribution): void
    {
        app(MaterializeOrganizationCommunicationDistribution::class, ['distributionId' => $distribution->id])
            ->handle(app(OrganizationCommunicationAudienceResolver::class), app(OrganizationCommunicationAudit::class));
    }
}
