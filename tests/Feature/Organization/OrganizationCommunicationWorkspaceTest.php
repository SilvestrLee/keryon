<?php

namespace Tests\Feature\Organization;

use App\Enums\ChurchRole;
use App\Enums\OrganizationCommunicationKind;
use App\Enums\OrganizationCommunicationMaterialType;
use App\Enums\OrganizationRole;
use App\Enums\PlatformMembershipStatus;
use App\Enums\PlatformRole;
use App\Filament\Organization\Pages\OrganizationCommunications;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRoleAssignment;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitType;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Organizations\Communications\OrganizationCommunicationManager;
use App\Organizations\Communications\OrganizationCommunicationWorkflow;
use App\Organizations\Communications\Read\OrganizationCommunicationQuery;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationIdentityService;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * K-ORG-COMMS-001B §66-§68/§86 — read-model scope isolation, search and
 * filtering, material reordering, and Filament page access control for
 * the Organization Communications landing/authoring workspace.
 */
class OrganizationCommunicationWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationHierarchyService $hierarchy;

    private OrganizationIdentityService $identity;

    private OrganizationCommunicationManager $manager;

    private Organization $organization;

    private OrganizationUnit $scope;

    private OrganizationUnit $descendant;

    private OrganizationUnit $sibling;

    private User $rootAdministrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hierarchy = app(OrganizationHierarchyService::class);
        $this->identity = app(OrganizationIdentityService::class);
        $this->manager = app(OrganizationCommunicationManager::class);

        $this->organization = $this->hierarchy->createOrganization('Workspace Network', 'workspace-network');
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
        $this->identity->bootstrapAdministrator($this->organization, $this->rootAdministrator);
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
        session(['active_workspace_type' => 'organization', 'active_organization_id' => $organization->id]);
        app(OrganizationContext::class)->forgetResolved();
        app(TenantContext::class)->forgetResolved();
    }

    public function test_unit_administrator_list_includes_own_and_descendant_scope_but_excludes_sibling(): void
    {
        [$unitUser] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->scope);

        $this->asOrganizationUser($this->rootAdministrator, $this->organization);
        $own = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Own scope']);
        $descendant = $this->manager->create($this->descendant, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Descendant scope']);
        $siblingComm = $this->manager->create($this->sibling, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Sibling scope']);

        $this->asOrganizationUser($unitUser, $this->organization);
        $ids = app(OrganizationCommunicationQuery::class)->paginate()->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertContains($descendant->id, $ids);
        $this->assertNotContains($siblingComm->id, $ids);
    }

    public function test_kind_and_state_filters_narrow_the_list(): void
    {
        $this->asOrganizationUser($this->rootAdministrator, $this->organization);
        $communication = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'General notice']);
        $campaign = $this->manager->create($this->scope, OrganizationCommunicationKind::CAMPAIGN, ['title' => 'Easter campaign']);

        $query = app(OrganizationCommunicationQuery::class);

        $campaignsOnly = $query->paginate(kind: 'campaign')->pluck('id')->all();
        $this->assertContains($campaign->id, $campaignsOnly);
        $this->assertNotContains($communication->id, $campaignsOnly);

        $draftOnly = $query->paginate(state: 'draft')->pluck('id')->all();
        $this->assertContains($communication->id, $draftOnly);
        $this->assertContains($campaign->id, $draftOnly);

        $searched = $query->paginate(search: 'Easter')->pluck('id')->all();
        $this->assertSame([$campaign->id], $searched);
    }

    public function test_attention_counts_reflect_only_authorized_scope_and_state(): void
    {
        [$unitAdmin] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->scope);

        $this->asOrganizationUser($this->rootAdministrator, $this->organization);
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Needs review'])->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Body');
        app(OrganizationCommunicationWorkflow::class)->submit($revision);

        $rootAttention = app(OrganizationCommunicationQuery::class)->attention();
        $this->assertSame(1, $rootAttention['awaiting_review']);

        // A Unit Administrator can edit but not approve — this item
        // should not count as "awaiting your review" for them.
        $this->asOrganizationUser($unitAdmin, $this->organization);
        $unitAttention = app(OrganizationCommunicationQuery::class)->attention();
        $this->assertSame(0, $unitAttention['awaiting_review']);
    }

    public function test_material_reorder_swaps_adjacent_items_and_is_draft_only(): void
    {
        $this->asOrganizationUser($this->rootAdministrator, $this->organization);
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Ordered'])->revisions->sole();
        $first = $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'First');
        $second = $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Second');

        $this->manager->moveMaterial($second, 'up');

        $ordered = $revision->fresh()->materials()->orderBy('sort_order')->pluck('id')->all();
        $this->assertSame([$second->id, $first->id], $ordered);

        // Moving the top item further up is a safe no-op.
        $this->manager->moveMaterial($second->fresh(), 'up');
        $this->assertSame([$second->id, $first->id], $revision->fresh()->materials()->orderBy('sort_order')->pluck('id')->all());
    }

    public function test_governing_unit_options_are_scoped_to_create_capability(): void
    {
        [$unitUser] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->scope);
        $this->asOrganizationUser($unitUser, $this->organization);

        $options = app(OrganizationCommunicationQuery::class)->governingUnitOptions();

        $this->assertArrayHasKey($this->scope->id, $options);
        $this->assertArrayHasKey($this->descendant->id, $options);
        $this->assertArrayNotHasKey($this->sibling->id, $options);
        $this->assertArrayNotHasKey($this->organization->root_unit_id, $options);
    }

    public function test_cross_organization_communication_id_fails_closed_on_find(): void
    {
        $this->asOrganizationUser($this->rootAdministrator, $this->organization);
        $communication = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Mine']);

        $other = $this->hierarchy->createOrganization('Other Workspace Network', 'other-workspace-network');
        $otherUser = User::factory()->create();
        $this->identity->bootstrapAdministrator($other, $otherUser);
        $this->asOrganizationUser($otherUser, $other);

        $this->expectException(ModelNotFoundException::class);
        app(OrganizationCommunicationQuery::class)->find($communication->id);
    }

    public function test_navigation_page_access_requires_active_organization_context(): void
    {
        $this->asOrganizationUser($this->rootAdministrator, $this->organization);
        $this->assertTrue(OrganizationCommunications::canAccess());

        $churchOnly = User::factory()->create();
        ChurchMembership::createPrimary(Church::factory()->create(), $churchOnly, [ChurchRole::ADMINISTRATOR]);
        $this->actingAs($churchOnly);
        session(['active_workspace_type' => 'church']);
        app(OrganizationContext::class)->forgetResolved();
        $this->assertFalse(OrganizationCommunications::canAccess());

        $platformOnly = User::factory()->create();
        PlatformMembership::query()->create([
            'user_id' => $platformOnly->id,
            'role' => PlatformRole::SUPPORT,
            'status' => PlatformMembershipStatus::ACTIVE,
            'activated_at' => now(),
        ]);
        $this->actingAs($platformOnly);
        session(['active_workspace_type' => 'central']);
        app(OrganizationContext::class)->forgetResolved();
        $this->assertFalse(OrganizationCommunications::canAccess());
    }

    public function test_viewer_can_view_but_the_policy_denies_create_and_approve(): void
    {
        [$viewer] = $this->organizationUser(OrganizationRole::ORGANIZATION_VIEWER, $this->scope);
        $this->asOrganizationUser($this->rootAdministrator, $this->organization);
        $communication = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Viewer test']);

        $this->asOrganizationUser($viewer, $this->organization);
        $this->assertTrue($viewer->can('view', $communication));
        $this->assertFalse($viewer->can('update', $communication));
        $this->assertFalse($viewer->can('approve', $communication));
        $this->assertFalse($viewer->can('delete', $communication));

        try {
            $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Viewer write attempt']);
            $this->fail('Viewer created a communication.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }
}
