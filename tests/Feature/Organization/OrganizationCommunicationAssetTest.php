<?php

namespace Tests\Feature\Organization;

use App\Enums\ChurchRole;
use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationCommunicationAssetRightsBasis;
use App\Enums\OrganizationCommunicationKind;
use App\Enums\OrganizationCommunicationMaterialType;
use App\Enums\OrganizationRole;
use App\Enums\PlatformMembershipStatus;
use App\Enums\PlatformRole;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Organization;
use App\Models\OrganizationAuditEvent;
use App\Models\OrganizationCommunicationAsset;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRoleAssignment;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitType;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Organizations\Communications\OrganizationCommunicationAssetManager;
use App\Organizations\Communications\OrganizationCommunicationManager;
use App\Organizations\Communications\OrganizationCommunicationWorkflow;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationIdentityService;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

/**
 * K-ORG-COMMS-001B §86-§89 — Organization communication asset lifecycle,
 * storage contract, and authorization boundary. Exercises
 * OrganizationCommunicationAssetManager::addAsset()/removeAsset()
 * directly (real fake-disk bytes) rather than driving Filament's upload
 * component, mirroring MediaIngestionTest's own stated rationale.
 */
class OrganizationCommunicationAssetTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationHierarchyService $hierarchy;

    private OrganizationIdentityService $identity;

    private OrganizationCommunicationManager $manager;

    private OrganizationCommunicationWorkflow $workflow;

    private OrganizationCommunicationAssetManager $assets;

    private Organization $organization;

    private OrganizationUnit $scope;

    private OrganizationUnit $sibling;

    private User $rootAdministrator;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media-private');

        $this->hierarchy = app(OrganizationHierarchyService::class);
        $this->identity = app(OrganizationIdentityService::class);
        $this->manager = app(OrganizationCommunicationManager::class);
        $this->workflow = app(OrganizationCommunicationWorkflow::class);
        $this->assets = app(OrganizationCommunicationAssetManager::class);

        $this->organization = $this->hierarchy->createOrganization('Asset Network', 'asset-network');
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
        $this->sibling = $this->hierarchy->createUnit($this->organization, $type, $this->organization->rootUnit, [
            'name' => 'Abuja Region',
            'code' => 'abuja',
        ]);

        $this->rootAdministrator = User::factory()->create();
        $this->identity->bootstrapAdministrator($this->organization, $this->rootAdministrator);
        $this->asOrganizationUser($this->rootAdministrator, $this->organization);
    }

    private function fakePngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    private function stageUpload(string $filename, ?string $bytes = null): string
    {
        // Uses the real target Organization's uuid directly (not the
        // caller's resolved context) so unauthorized-identity tests can
        // stage a syntactically valid path and still prove the
        // authorizer — not a null-context crash — is what denies them.
        $stagingPath = "organizations/{$this->organization->uuid}/communications/.staging/".uniqid().'.tmp';
        Storage::disk('media-private')->put($stagingPath, $bytes ?? $this->fakePngBytes());

        return $stagingPath;
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

    public function test_asset_is_stored_under_the_canonical_organization_communication_revision_path(): void
    {
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::CAMPAIGN, ['title' => 'Outreach'])
            ->revisions->sole();
        $staged = $this->stageUpload('flyer.png');

        $asset = $this->assets->addAsset($revision, $staged, 'flyer.png', [
            'rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION,
        ]);

        $this->assertStringStartsWith("organizations/{$this->organization->uuid}/communications/", $asset->path);
        $this->assertStringContainsString("/{$revision->version}/", $asset->path);
        $this->assertStringEndsWith('/original.png', $asset->path);
        Storage::disk('media-private')->assertExists($asset->path);
        Storage::disk('media-private')->assertMissing($staged);
        $this->assertSame($this->organization->id, $asset->organization_id);
        $this->assertSame($revision->id, $asset->organization_communication_revision_id);
    }

    public function test_unsupported_mime_type_is_rejected(): void
    {
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Notice'])
            ->revisions->sole();
        $staged = $this->stageUpload('notes.txt', 'not an approved file type');

        $this->expectException(ValidationException::class);
        $this->assets->addAsset($revision, $staged, 'notes.txt', ['rights_basis' => OrganizationCommunicationAssetRightsBasis::PUBLIC_DOMAIN]);
    }

    public function test_oversized_upload_is_rejected(): void
    {
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Notice'])
            ->revisions->sole();
        $staged = $this->stageUpload('large.png');
        Storage::disk('media-private')->append($staged, str_repeat('x', (OrganizationCommunicationAssetManager::MAX_UPLOAD_SIZE_KB * 1024) + 1));

        $this->expectException(ValidationException::class);
        $this->assets->addAsset($revision, $staged, 'large.png', ['rights_basis' => OrganizationCommunicationAssetRightsBasis::PUBLIC_DOMAIN]);
    }

    public function test_attribution_required_without_attribution_text_is_rejected(): void
    {
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Notice'])
            ->revisions->sole();
        $staged = $this->stageUpload('flyer.png');

        $this->expectException(ValidationException::class);
        $this->assets->addAsset($revision, $staged, 'flyer.png', [
            'rights_basis' => OrganizationCommunicationAssetRightsBasis::LICENSED_FOR_CAMPAIGN,
            'attribution_required' => true,
        ]);
    }

    public function test_invalid_rights_basis_is_rejected(): void
    {
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Notice'])
            ->revisions->sole();
        $staged = $this->stageUpload('flyer.png');

        $this->expectException(ValidationException::class);
        $this->assets->addAsset($revision, $staged, 'flyer.png', ['rights_basis' => 'not-a-real-basis']);
    }

    public function test_asset_addition_is_authorized_and_audited_without_leaking_filenames(): void
    {
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Notice'])
            ->revisions->sole();
        $staged = $this->stageUpload('confidential-name.png');

        $asset = $this->assets->addAsset($revision, $staged, 'confidential-name.png', [
            'rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION,
        ]);

        $event = OrganizationAuditEvent::query()
            ->where('event_type', OrganizationAuditEventType::COMMUNICATION_ASSET_ADDED->value)
            ->where('subject_id', $asset->id)
            ->sole();
        $this->assertStringNotContainsString('confidential-name', $event->toJson());
    }

    public function test_asset_mutation_requires_draft_revision(): void
    {
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Notice'])
            ->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Body');
        $staged = $this->stageUpload('flyer.png');
        $asset = $this->assets->addAsset($revision, $staged, 'flyer.png', ['rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION]);

        $approved = $this->workflow->approve($this->workflow->submit($revision));

        $this->expectException(LogicException::class);
        $asset->fresh()->forceFill(['alt_text' => 'changed'])->save();
    }

    public function test_approved_revision_asset_cannot_be_deleted(): void
    {
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Notice'])
            ->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Body');
        $staged = $this->stageUpload('flyer.png');
        $asset = $this->assets->addAsset($revision, $staged, 'flyer.png', ['rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION]);
        $this->workflow->approve($this->workflow->submit($revision));

        // The actor is still authorized to act on the communication —
        // it is the Draft-only lifecycle rule that blocks this, matching
        // OrganizationCommunicationMaterial's identical immutability rule.
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Draft revision');
        $this->assets->removeAsset($asset->fresh());
    }

    public function test_next_revision_copies_asset_records_referencing_the_same_stored_file(): void
    {
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Notice'])
            ->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Body');
        $staged = $this->stageUpload('flyer.png');
        $original = $this->assets->addAsset($revision, $staged, 'flyer.png', ['rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION]);
        $approved = $this->workflow->approve($this->workflow->submit($revision));

        $next = $this->manager->createNextRevision($approved);
        $copy = $next->assets->sole();

        $this->assertNotSame($original->id, $copy->id);
        $this->assertSame($original->path, $copy->path);
        $this->assertSame($original->sha256, $copy->sha256);
        $this->assertSame($original->rights_basis, $copy->rights_basis);

        // Deleting the Draft copy must not remove the still-referenced
        // physical file — the approved revision's asset row depends on it.
        $this->assets->removeAsset($copy);
        Storage::disk('media-private')->assertExists($original->path);
        $this->assertSame(0, OrganizationCommunicationAsset::query()->where('id', $copy->id)->count());
    }

    public function test_last_referencing_asset_deletion_removes_the_physical_file(): void
    {
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Notice'])
            ->revisions->sole();
        $staged = $this->stageUpload('flyer.png');
        $asset = $this->assets->addAsset($revision, $staged, 'flyer.png', ['rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION]);
        $path = $asset->path;

        $this->assets->removeAsset($asset);

        Storage::disk('media-private')->assertMissing($path);
    }

    public function test_unit_administrator_cannot_add_asset_in_sibling_scope(): void
    {
        [$unitUser] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->scope);
        $communication = $this->manager->create($this->sibling, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Sibling']);
        $revision = $communication->revisions->sole();

        $this->asOrganizationUser($unitUser, $this->organization);
        $staged = $this->stageUpload('flyer.png');

        $this->expectException(AuthorizationException::class);
        $this->assets->addAsset($revision, $staged, 'flyer.png', ['rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION]);
    }

    public function test_viewer_church_only_and_platform_only_identities_cannot_add_assets(): void
    {
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Notice'])
            ->revisions->sole();

        [$viewer] = $this->organizationUser(OrganizationRole::ORGANIZATION_VIEWER, $this->scope);
        $this->asOrganizationUser($viewer, $this->organization);
        $staged = $this->stageUpload('flyer.png');
        try {
            $this->assets->addAsset($revision, $staged, 'flyer.png', ['rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION]);
            $this->fail('Viewer was able to add an asset.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $churchOnly = User::factory()->create();
        ChurchMembership::createPrimary(Church::factory()->create(), $churchOnly, [ChurchRole::ADMINISTRATOR]);
        $this->asOrganizationUser($churchOnly, $this->organization);
        $staged = $this->stageUpload('flyer.png');
        try {
            $this->assets->addAsset($revision, $staged, 'flyer.png', ['rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION]);
            $this->fail('Church-only identity was able to add an asset.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $platformOnly = User::factory()->create();
        PlatformMembership::query()->create([
            'user_id' => $platformOnly->id,
            'role' => PlatformRole::SUPPORT,
            'status' => PlatformMembershipStatus::ACTIVE,
            'activated_at' => now(),
        ]);
        $this->asOrganizationUser($platformOnly, $this->organization);
        $staged = $this->stageUpload('flyer.png');
        $this->expectException(AuthorizationException::class);
        $this->assets->addAsset($revision, $staged, 'flyer.png', ['rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION]);
    }

    public function test_cross_organization_asset_cannot_be_viewed_or_mutated(): void
    {
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Notice'])
            ->revisions->sole();
        $staged = $this->stageUpload('flyer.png');
        $asset = $this->assets->addAsset($revision, $staged, 'flyer.png', ['rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION]);

        $other = $this->hierarchy->createOrganization('Other Asset Network', 'other-asset-network');
        $otherUser = User::factory()->create();
        $this->identity->bootstrapAdministrator($other, $otherUser);
        $this->asOrganizationUser($otherUser, $other);

        $this->assertFalse($otherUser->can('view', $asset));
        $this->assertFalse($otherUser->can('delete', $asset));
    }

    public function test_stale_membership_cannot_mutate_assets(): void
    {
        [$unitUser, $membership] = $this->organizationUser(OrganizationRole::UNIT_ADMINISTRATOR, $this->scope);
        $this->asOrganizationUser($unitUser, $this->organization);
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Notice'])
            ->revisions->sole();
        $staged = $this->stageUpload('flyer.png');
        $asset = $this->assets->addAsset($revision, $staged, 'flyer.png', ['rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION]);

        $this->identity->suspend($membership, $this->rootAdministrator->id);

        $this->expectException(AuthorizationException::class);
        $this->assets->removeAsset($asset);
    }

    public function test_asset_rights_basis_options_are_bounded(): void
    {
        $this->assertSame(
            ['owned_by_organization', 'licensed_for_campaign', 'permission_obtained', 'public_domain'],
            array_map(fn ($case) => $case->value, OrganizationCommunicationAssetRightsBasis::cases()),
        );
    }
}
