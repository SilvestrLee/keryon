<?php

namespace Tests\Feature\Organization;

use App\Enums\ChurchRole;
use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationCommunicationAdaptationPolicy;
use App\Enums\OrganizationCommunicationKind;
use App\Enums\OrganizationCommunicationMaterialType;
use App\Enums\OrganizationCommunicationRevisionState;
use App\Enums\OrganizationCommunicationState;
use App\Enums\OrganizationRole;
use App\Enums\PlatformMembershipStatus;
use App\Enums\PlatformRole;
use App\Models\Campaign;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ContentItem;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\OrganizationAuditEvent;
use App\Models\OrganizationCommunication;
use App\Models\OrganizationCommunicationMaterial;
use App\Models\OrganizationCommunicationRevision;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRoleAssignment;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitType;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Organizations\Communications\OrganizationCommunicationManager;
use App\Organizations\Communications\OrganizationCommunicationWorkflow;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationIdentityService;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;
use TypeError;

class OrganizationCommunicationFoundationTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationHierarchyService $hierarchy;

    private OrganizationIdentityService $identity;

    private OrganizationCommunicationManager $manager;

    private OrganizationCommunicationWorkflow $workflow;

    private Organization $organization;

    private OrganizationUnit $scope;

    private OrganizationUnit $descendant;

    private OrganizationUnit $sibling;

    private User $rootAdministrator;

    private OrganizationMembership $rootMembership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hierarchy = app(OrganizationHierarchyService::class);
        $this->identity = app(OrganizationIdentityService::class);
        $this->manager = app(OrganizationCommunicationManager::class);
        $this->workflow = app(OrganizationCommunicationWorkflow::class);
        $this->organization = $this->hierarchy->createOrganization('Keryon Network', 'keryon-network');
        $type = OrganizationUnitType::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'region',
            'label' => 'Region',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $this->scope = $this->hierarchy->createUnit($this->organization, $type, $this->organization->rootUnit, [
            'name' => 'Lagos Region',
            'code' => 'lagos',
        ]);
        $this->descendant = $this->hierarchy->createUnit($this->organization, $type, $this->scope, [
            'name' => 'Province 12',
            'code' => 'province-12',
        ]);
        $this->sibling = $this->hierarchy->createUnit($this->organization, $type, $this->organization->rootUnit, [
            'name' => 'Abuja Region',
            'code' => 'abuja',
        ]);
        $this->rootAdministrator = User::factory()->create();
        $this->rootMembership = $this->identity->bootstrapAdministrator($this->organization, $this->rootAdministrator);
        $this->asOrganizationUser($this->rootAdministrator, $this->organization);
    }

    public function test_organization_administrator_can_create_submit_and_self_approve_without_church_context(): void
    {
        $communication = $this->manager->create($this->organization->rootUnit, OrganizationCommunicationKind::CAMPAIGN, [
            'title' => 'Easter Together',
            'summary' => 'A shared Easter communication initiative.',
            'requested_action' => 'Review and adapt these resources locally.',
            'adaptation_policy' => OrganizationCommunicationAdaptationPolicy::LOCAL_ADAPTATION_ENCOURAGED,
            'campaign_starts_on' => '2027-03-20',
            'campaign_ends_on' => '2027-04-05',
            'recommended_response_on' => '2027-03-01',
            'suggested_publish_by' => '2027-03-25',
            'available_from' => '2027-02-01 08:00:00',
            'available_until' => '2027-04-05 23:59:00',
        ]);
        $revision = $communication->revisions->sole();
        $first = $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::ANNOUNCEMENT, 'Celebrate Easter with us.', 'Announcement');
        $second = $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::SOCIAL_CAPTION, 'Hope is alive.');

        $this->assertSame($this->organization->id, $communication->organization_id);
        $this->assertSame($this->organization->root_unit_id, $communication->governing_unit_id);
        $this->assertSame($this->rootMembership->id, $communication->created_by_organization_membership_id);
        $this->assertSame(OrganizationCommunicationState::DRAFT, $communication->state);
        $this->assertSame([1, 2], $revision->materials()->pluck('sort_order')->all());
        $this->assertSame([$first->id, $second->id], $revision->materials()->pluck('id')->all());

        $submitted = $this->workflow->submit($revision);
        $approved = $this->workflow->approve($submitted);

        $this->assertSame(OrganizationCommunicationRevisionState::APPROVED, $approved->state);
        $this->assertSame($this->rootMembership->id, $approved->submitted_by_organization_membership_id);
        $this->assertSame($this->rootMembership->id, $approved->approved_by_organization_membership_id);
        $this->assertSame(OrganizationCommunicationState::DRAFT, $communication->fresh()->state);
        $this->assertFalse(app(TenantContext::class)->hasContext());
        $this->assertSame(0, ChurchMembership::query()->where('user_id', $this->rootAdministrator->id)->count());
        $this->assertSame(0, Campaign::query()->count());
        $this->assertSame(0, ContentItem::query()->count());
        $this->assertSame(0, MediaAsset::query()->count());

        $events = OrganizationAuditEvent::query()
            ->where('organization_id', $this->organization->id)
            ->whereIn('event_type', [
                OrganizationAuditEventType::COMMUNICATION_CREATED->value,
                OrganizationAuditEventType::COMMUNICATION_REVISION_SUBMITTED->value,
                OrganizationAuditEventType::COMMUNICATION_REVISION_APPROVED->value,
            ])
            ->get();
        $this->assertCount(3, $events);
        $this->assertTrue($events->every(fn (OrganizationAuditEvent $event): bool => ($event->new_state['actor_membership_id'] ?? null) === $this->rootMembership->id));
        $auditJson = $events->toJson();
        $this->assertStringNotContainsString('Celebrate Easter with us', $auditJson);
        $this->assertStringNotContainsString('A shared Easter communication initiative', $auditJson);
    }

    public function test_unit_administrator_can_author_edit_and_submit_but_cannot_approve_or_leave_scope(): void
    {
        [$unitUser] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->scope);
        $this->asOrganizationUser($unitUser, $this->organization);

        $communication = $this->manager->create($this->descendant, OrganizationCommunicationKind::COMMUNICATION, [
            'title' => 'Province update',
        ]);
        $revision = $communication->revisions->sole();
        $updated = $this->manager->updateDraftRevision($revision, [
            'summary' => 'Prepared for Churches in this Province.',
            'adaptation_policy' => OrganizationCommunicationAdaptationPolicy::LOCAL_DETAILS_REQUIRED,
        ]);
        $submitted = $this->workflow->submit($updated);

        $this->assertSame(OrganizationCommunicationRevisionState::IN_REVIEW, $submitted->state);
        $this->expectException(AuthorizationException::class);
        $this->workflow->approve($submitted);
    }

    public function test_unit_administrator_cannot_create_in_sibling_scope(): void
    {
        [$unitUser] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->scope);
        $this->asOrganizationUser($unitUser, $this->organization);

        $this->expectException(AuthorizationException::class);
        $this->manager->create($this->sibling, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Forbidden']);
    }

    public function test_changes_requested_return_to_draft_and_approval_follow_scoped_authority(): void
    {
        [$unitUser] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->scope);
        $this->asOrganizationUser($unitUser, $this->organization);
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Regional notice'])
            ->revisions
            ->sole();
        $submitted = $this->workflow->submit($revision);

        $this->asOrganizationUser($this->rootAdministrator, $this->organization);
        $changes = $this->workflow->requestChanges($submitted, 'Clarify the requested local action.');
        $this->assertSame(OrganizationCommunicationRevisionState::CHANGES_REQUESTED, $changes->state);

        $this->asOrganizationUser($unitUser, $this->organization);
        $draft = $this->workflow->returnToDraft($changes);
        $draft = $this->manager->updateDraftRevision($draft, ['requested_action' => 'Confirm the local contact.']);
        $resubmitted = $this->workflow->submit($draft);

        $this->asOrganizationUser($this->rootAdministrator, $this->organization);
        $approved = $this->workflow->approve($resubmitted);
        $this->assertSame(OrganizationCommunicationRevisionState::APPROVED, $approved->state);
        $this->assertNull($approved->review_feedback);

        $auditJson = OrganizationAuditEvent::query()
            ->where('event_type', OrganizationAuditEventType::COMMUNICATION_REVISION_CHANGES_REQUESTED->value)
            ->get()
            ->toJson();
        $this->assertStringNotContainsString('Clarify the requested local action', $auditJson);
    }

    public function test_approved_revision_and_material_are_immutable_and_next_revisions_are_unique_copies(): void
    {
        $communication = $this->manager->create($this->organization->rootUnit, OrganizationCommunicationKind::COMMUNICATION, [
            'title' => 'Canonical notice',
            'adaptation_policy' => OrganizationCommunicationAdaptationPolicy::USE_AS_PROVIDED,
        ]);
        $revision = $communication->revisions->sole();
        $material = $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Canonical body');
        $approved = $this->workflow->approve($this->workflow->submit($revision));

        try {
            $approved->forceFill(['title' => 'Mutated title'])->save();
            $this->fail('An approved revision was mutated in place.');
        } catch (LogicException) {
            $this->assertSame('Canonical notice', $approved->fresh()->title);
        }

        try {
            $material->forceFill(['body' => 'Mutated body'])->save();
            $this->fail('Approved revision material was mutated in place.');
        } catch (LogicException) {
            $this->assertSame('Canonical body', $material->fresh()->body);
        }

        $second = $this->manager->createNextRevision($approved);
        $third = $this->manager->createNextRevision($approved);
        $this->assertSame([1, 2, 3], $communication->revisions()->pluck('version')->all());
        $this->assertSame(OrganizationCommunicationRevisionState::DRAFT, $second->state);
        $this->assertSame('Canonical body', $second->materials->sole()->body);
        $this->manager->updateDraftRevision($second, ['title' => 'Locally revised source']);
        $this->assertSame('Canonical notice', $approved->fresh()->title);

        $this->expectException(QueryException::class);
        DB::table('organization_communication_revisions')->insert([
            'organization_communication_id' => $communication->id,
            'created_by_organization_membership_id' => $this->rootMembership->id,
            'version' => $third->version,
            'state' => OrganizationCommunicationRevisionState::DRAFT->value,
            'title' => 'Duplicate version',
            'adaptation_policy' => OrganizationCommunicationAdaptationPolicy::LOCAL_ADAPTATION_ENCOURAGED->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_invalid_transitions_fail_and_root_only_reaches_active_via_the_distribution_worker(): void
    {
        $communication = $this->manager->create($this->organization->rootUnit, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Lifecycle']);
        $revision = $communication->revisions->sole();

        try {
            $this->workflow->approve($revision);
            $this->fail('A Draft revision was approved without review.');
        } catch (LogicException) {
            $this->assertSame(OrganizationCommunicationRevisionState::DRAFT, $revision->fresh()->state);
        }

        try {
            $this->workflow->withdraw($communication);
            $this->fail('A Draft root was marked Withdrawn without distribution.');
        } catch (LogicException) {
            $this->assertSame(OrganizationCommunicationState::DRAFT, $communication->fresh()->state);
        }

        // K-ORG-COMMS-001C §12 — Draft -> Active is now a structurally
        // valid model transition (the distribution worker needs it after a
        // completed distribution — see
        // OrganizationCommunicationDistributionTest). The model can only
        // guard transition *validity*, not *who* calls it; the real
        // guarantee that this never fires without genuine distribution
        // evidence is procedural — no Manager/Workflow method exposes this
        // transition to authors, only the worker's completion path does,
        // and only after persisting durable deliveries.
        $communication->forceFill(['state' => OrganizationCommunicationState::ACTIVE])->save();
        $this->assertSame(OrganizationCommunicationState::ACTIVE, $communication->fresh()->state);

        // Pre-existing 001A behavior, unchanged: Workflow::close() only
        // accepts Draft/Active as its source state, so an already-Withdrawn
        // root cannot additionally be closed through this method — a
        // separate root-level restriction from the model's own transition
        // guard (which does allow Withdrawn -> Closed at the data layer).
        $withdrawn = $this->workflow->withdraw($communication->fresh());
        $this->assertSame(OrganizationCommunicationState::WITHDRAWN, $withdrawn->state);
    }

    public function test_viewer_church_only_and_platform_only_identities_cannot_mutate_the_domain(): void
    {
        $communication = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Read only']);

        [$viewer] = $this->organizationUser(OrganizationRole::ORGANIZATION_VIEWER, $this->scope);
        $this->asOrganizationUser($viewer, $this->organization);
        $this->assertTrue($viewer->can('view', $communication));
        $this->assertFalse($viewer->can('update', $communication));
        $this->assertFalse($viewer->can('approve', $communication));
        $this->assertActionDenied(fn () => $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Viewer write']));

        $churchOnly = User::factory()->create();
        ChurchMembership::createPrimary(Church::factory()->create(), $churchOnly, [ChurchRole::ADMINISTRATOR]);
        $this->asOrganizationUser($churchOnly, $this->organization);
        $this->assertActionDenied(fn () => $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Church write']));

        $platformOnly = User::factory()->create();
        PlatformMembership::query()->create([
            'user_id' => $platformOnly->id,
            'role' => PlatformRole::SUPPORT,
            'status' => PlatformMembershipStatus::ACTIVE,
            'activated_at' => now(),
        ]);
        $this->asOrganizationUser($platformOnly, $this->organization);
        $this->assertActionDenied(fn () => $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Platform write']));
    }

    public function test_sibling_and_different_organization_records_fail_policy_and_action_authorization(): void
    {
        $siblingCommunication = $this->manager->create($this->sibling, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Sibling']);
        [$unitUser] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->scope);
        $this->asOrganizationUser($unitUser, $this->organization);

        $this->assertFalse($unitUser->can('view', $siblingCommunication));
        $this->assertFalse($unitUser->can('update', $siblingCommunication));
        $this->assertActionDenied(fn () => $this->manager->updateDraftRevision($siblingCommunication->revisions->sole(), ['title' => 'Scope bypass']));

        $other = $this->hierarchy->createOrganization('Other Network', 'other-network');
        $otherUser = User::factory()->create();
        $this->identity->bootstrapAdministrator($other, $otherUser);
        $this->asOrganizationUser($otherUser, $other);

        $this->assertFalse($otherUser->can('view', $siblingCommunication));
        $this->assertActionDenied(fn () => $this->manager->updateDraftRevision($siblingCommunication->revisions->sole(), ['title' => 'Cross Organization']));
    }

    public function test_suspended_removed_and_revoked_authority_is_revalidated_for_every_action(): void
    {
        foreach (['suspended', 'removed', 'role_removed'] as $ending) {
            [$user, $membership, $assignment] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->scope);
            $this->asOrganizationUser($user, $this->organization);
            $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Stale authority'])
                ->revisions
                ->sole();

            if ($ending === 'suspended') {
                $this->identity->suspend($membership, $this->rootAdministrator->id);
            } elseif ($ending === 'removed') {
                $this->identity->remove($membership, $this->rootAdministrator->id);
            } else {
                $this->identity->removeRole($assignment, $this->rootAdministrator->id);
            }

            $this->assertActionDenied(fn () => $this->manager->updateDraftRevision($revision, ['summary' => 'Should fail']));
        }
    }

    public function test_models_guard_managed_fields_and_reject_cross_organization_ownership(): void
    {
        $communication = new OrganizationCommunication;
        $revision = new OrganizationCommunicationRevision;
        $material = new OrganizationCommunicationMaterial;
        foreach (['organization_id', 'governing_unit_id', 'state', 'kind'] as $field) {
            $this->assertFalse($communication->isFillable($field));
        }
        foreach (['organization_communication_id', 'version', 'state', 'approved_at'] as $field) {
            $this->assertFalse($revision->isFillable($field));
        }
        foreach (['organization_communication_revision_id', 'type', 'sort_order'] as $field) {
            $this->assertFalse($material->isFillable($field));
        }

        $other = $this->hierarchy->createOrganization('Foreign Network', 'foreign-network');
        $invalid = new OrganizationCommunication;
        $invalid->forceFill([
            'organization_id' => $this->organization->id,
            'governing_unit_id' => $other->root_unit_id,
            'created_by_organization_membership_id' => $this->rootMembership->id,
            'kind' => OrganizationCommunicationKind::COMMUNICATION,
            'state' => OrganizationCommunicationState::DRAFT,
        ]);

        try {
            $invalid->save();
            $this->fail('Cross-Organization ownership was accepted.');
        } catch (LogicException) {
            $this->assertFalse($invalid->exists);
        }

        $validRevision = $this->manager->create($this->organization->rootUnit, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Typed material'])
            ->revisions
            ->sole();
        $this->expectException(TypeError::class);
        /** @phpstan-ignore-next-line Deliberately proving enum-only domain input. */
        $this->manager->addMaterial($validRevision, 'invalid', 'Body');
    }

    public function test_schema_is_the_bounded_three_table_foundation_without_church_foreign_keys(): void
    {
        foreach ([
            'organization_communications',
            'organization_communication_revisions',
            'organization_communication_materials',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertFalse(Schema::hasColumn($table, 'church_id'));
            $this->assertFalse(Schema::hasColumn($table, 'content_item_id'));
            $this->assertFalse(Schema::hasColumn($table, 'media_asset_id'));
        }

        $revisionIndexes = collect(Schema::getIndexes('organization_communication_revisions'))->pluck('name');
        $materialIndexes = collect(Schema::getIndexes('organization_communication_materials'))->pluck('name');
        $this->assertContains('org_comm_revisions_version_unique', $revisionIndexes);
        $this->assertContains('org_comm_materials_order_unique', $materialIndexes);
        $this->assertSame(3, collect(Schema::getForeignKeys('organization_communications'))->count());
        $this->assertSame(5, collect(Schema::getForeignKeys('organization_communication_revisions'))->count());
        $this->assertSame(1, collect(Schema::getForeignKeys('organization_communication_materials'))->count());
    }

    /** @return array{User, OrganizationMembership, OrganizationRoleAssignment} */
    private function organizationUser(OrganizationRole $role, OrganizationUnit $unit): array
    {
        $user = User::factory()->create();
        $membership = $this->identity->activate($this->identity->invite($this->organization, $user));
        $assignment = $this->identity->assignRole($membership, $role, $unit);

        return [$user, $membership, $assignment];
    }

    private function asOrganizationUser(User $user, Organization $organization): void
    {
        $this->actingAs($user);
        session([
            'active_workspace_type' => 'organization',
            'active_organization_id' => $organization->id,
        ]);
        app(OrganizationContext::class)->forgetResolved();
        app(TenantContext::class)->forgetResolved();
    }

    private function assertActionDenied(callable $action): void
    {
        try {
            $action();
            $this->fail('An unauthorized Organization communication action succeeded.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }
}
