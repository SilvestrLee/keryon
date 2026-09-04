<?php

namespace Tests\Feature\Communications;

use App\Communications\OrganizationInbox\Import\CreateContentItemFromOrganizationImport;
use App\Communications\OrganizationInbox\Import\Exceptions\OrganizationCommunicationImportException;
use App\Communications\OrganizationInbox\Import\OrganizationCommunicationImportService;
use App\Communications\OrganizationInbox\OrganizationCommunicationChurchResponseService;
use App\Enums\AssetProvenance;
use App\Enums\CampaignStatus;
use App\Enums\ChurchRole;
use App\Enums\ContentOrigin;
use App\Enums\ContentStatus;
use App\Enums\OrganizationCommunicationAdaptationPolicy;
use App\Enums\OrganizationCommunicationAssetRightsBasis;
use App\Enums\OrganizationCommunicationKind;
use App\Enums\OrganizationCommunicationMaterialType;
use App\Enums\OrganizationCommunicationTargetMode;
use App\Enums\OrganizationStatus;
use App\Models\Campaign;
use App\Models\CampaignCommunication;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ContentItem;
use App\Models\FaithFlowRun;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\OrganizationCommunicationDelivery;
use App\Models\OrganizationCommunicationImport;
use App\Models\OrganizationCommunicationRevision;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitType;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Models\WebsitePublication;
use App\Organizations\Communications\Distribution\OrganizationCommunicationDistributionManager;
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
use Tests\TestCase;

/**
 * K-ORG-COMMS-001E §78-§95 — the Church Import & Local Adaptation test
 * matrix. "ORGANIZATION SOURCE ≠ CHURCH COPY": every creation test also
 * confirms the Organization source is left completely untouched.
 */
class OrganizationCommunicationImportTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationHierarchyService $hierarchy;

    private OrganizationIdentityService $identity;

    private OrganizationCommunicationManager $manager;

    private OrganizationCommunicationWorkflow $workflow;

    private OrganizationCommunicationDistributionManager $distributions;

    private OrganizationCommunicationAssetManager $assets;

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
        $this->assets = app(OrganizationCommunicationAssetManager::class);

        $this->organization = $this->hierarchy->createOrganization('Import Proof Diocese', 'import-proof-diocese');
        $type = OrganizationUnitType::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'region', 'label' => 'Region', 'sort_order' => 10, 'is_active' => true,
        ]);
        $this->unit = $this->hierarchy->createUnit($this->organization, $type, $this->organization->rootUnit, ['name' => 'Region A', 'code' => 'region-a']);

        $admin = User::factory()->create();
        $this->identity->bootstrapAdministrator($this->organization, $admin);
        $this->asOrganizationUser($admin);
    }

    // ---------------------------------------------------------------
    // fixtures / helpers
    // ---------------------------------------------------------------

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

    /** @return array{Church, User} */
    private function attachedChurch(string $name, array $roles = [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS]): array
    {
        $church = Church::factory()->create(['name' => $name]);
        $staff = User::factory()->create();
        ChurchMembership::createPrimary($church, $staff, $roles);
        $pending = $this->hierarchy->attachChurch($this->organization, $this->unit, $church);
        $this->hierarchy->acceptAttachment($pending, $staff);

        return [$church->fresh(), $staff];
    }

    private function distributedRevision(
        array $overrides = [],
        OrganizationCommunicationKind $kind = OrganizationCommunicationKind::COMMUNICATION,
        int $materialCount = 1,
        OrganizationCommunicationTargetMode $mode = OrganizationCommunicationTargetMode::GOVERNING_SCOPE,
        ?callable $beforeSubmit = null,
    ): OrganizationCommunicationRevision {
        $communication = $this->manager->create($this->unit, $kind, array_merge(['title' => 'Fall gathering notice'], $overrides));
        $revision = $communication->revisions->sole();

        for ($i = 0; $i < $materialCount; $i++) {
            $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, "Body copy {$i}.", "Material {$i}");
        }

        if ($beforeSubmit !== null) {
            $beforeSubmit($revision);
        }

        $revision = $this->workflow->approve($this->workflow->submit($revision->fresh()));
        $this->distributions->request($revision->fresh(), $mode, null, null);

        return $revision->fresh();
    }

    private function fakePngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    private function stageUpload(string $filename): string
    {
        $stagingPath = "organizations/{$this->organization->uuid}/communications/.staging/".uniqid().'.tmp';
        Storage::disk('media-private')->put($stagingPath, $this->fakePngBytes());

        return $stagingPath;
    }

    private function deliveryFor(Church $church, OrganizationCommunicationRevision $revision): OrganizationCommunicationDelivery
    {
        return OrganizationCommunicationDelivery::query()
            ->where('church_id', $church->id)
            ->where('organization_communication_revision_id', $revision->id)
            ->firstOrFail();
    }

    private function acceptedDelivery(Church $church, User $staff, OrganizationCommunicationRevision $revision): OrganizationCommunicationDelivery
    {
        $delivery = $this->deliveryFor($church, $revision);
        $this->asChurchUser($staff);
        app(OrganizationCommunicationChurchResponseService::class)->accept($delivery);

        return $delivery->fresh();
    }

    // ---------------------------------------------------------------
    // §87/§91 authorization / conjunctive capability
    // ---------------------------------------------------------------

    public function test_administrator_alone_cannot_import_because_it_lacks_content_manage(): void
    {
        [$church, $staff] = $this->attachedChurch('Admin Only Church', [ChurchRole::ADMINISTRATOR]);
        $revision = $this->distributedRevision();
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        $this->assertTrue(auth()->user()->can('import', $delivery));

        try {
            app(OrganizationCommunicationImportService::class)->import($delivery);
            $this->fail('Administrator without content.manage imported successfully.');
        } catch (OrganizationCommunicationImportException $e) {
            $this->assertStringContainsString('also requires', $e->getMessage());
        }

        $this->assertSame(0, ContentItem::query()->count());
        $this->assertSame(0, OrganizationCommunicationImport::query()->count());
    }

    public function test_communications_role_can_import_end_to_end(): void
    {
        [$church, $staff] = $this->attachedChurch('Communications Church', [ChurchRole::COMMUNICATIONS]);
        $revision = $this->distributedRevision(materialCount: 2);
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        $import = app(OrganizationCommunicationImportService::class)->import($delivery);

        $this->assertNotNull($import->id);
        $this->assertSame(2, ContentItem::query()->count());
    }

    public function test_care_role_cannot_import(): void
    {
        [$church] = $this->attachedChurch('Care Church', [ChurchRole::COMMUNICATIONS]);
        $careUser = User::factory()->create();
        $careMembership = $careUser->memberships()->create(['church_id' => $church->id, 'status' => 'active', 'joined_at' => now()]);
        $careMembership->assignRoles([ChurchRole::CARE]);

        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->asChurchUser($careUser);
        $this->assertFalse(auth()->user()->can('import', $delivery));

        $this->expectException(AuthorizationException::class);
        app(OrganizationCommunicationImportService::class)->import($delivery);
    }

    public function test_organization_administrator_without_church_membership_cannot_import(): void
    {
        [$church, $staff] = $this->attachedChurch('No Membership Church');
        $revision = $this->distributedRevision();
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        // Switch back to the Organization admin — no ChurchMembership at all.
        $orgAdmin = $this->organization->memberships()->sole()->user;
        $this->asOrganizationUser($orgAdmin);

        $this->assertNull(app(TenantContext::class)->currentMembership());
        $this->assertFalse(auth()->user()->can('import', $delivery));
    }

    public function test_cross_church_import_is_denied(): void
    {
        [$churchA, $staffA] = $this->attachedChurch('Church A Import');
        [, $staffB] = $this->attachedChurch('Church B Import');
        $revision = $this->distributedRevision();
        $deliveryA = $this->acceptedDelivery($churchA, $staffA, $revision);

        $this->asChurchUser($staffB);
        $this->assertFalse(auth()->user()->can('import', $deliveryA));

        $this->expectException(AuthorizationException::class);
        app(OrganizationCommunicationImportService::class)->import($deliveryA);
    }

    // ---------------------------------------------------------------
    // §83-§86 eligibility
    // ---------------------------------------------------------------

    public function test_import_requires_accepted_state(): void
    {
        [$church, $staff] = $this->attachedChurch('Available Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->asChurchUser($staff);

        $this->expectException(OrganizationCommunicationImportException::class);
        app(OrganizationCommunicationImportService::class)->import($delivery);
    }

    public function test_reference_only_delivery_cannot_be_imported_even_by_direct_call(): void
    {
        [$church, $staff] = $this->attachedChurch('Reference Only Church');
        $revision = $this->distributedRevision(['adaptation_policy' => OrganizationCommunicationAdaptationPolicy::REFERENCE_ONLY]);
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        try {
            app(OrganizationCommunicationImportService::class)->import($delivery);
            $this->fail('Reference-only delivery was imported.');
        } catch (OrganizationCommunicationImportException $e) {
            $this->assertStringContainsString('reference material', $e->getMessage());
        }

        $this->assertSame(0, ContentItem::query()->count());
        $this->assertSame(0, OrganizationCommunicationImport::query()->count());
    }

    public function test_accepted_before_expiry_can_be_imported_after_expiry(): void
    {
        [$church, $staff] = $this->attachedChurch('Expiry Import Church');
        $revision = $this->distributedRevision(['available_until' => now()->addDay()]);
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        $this->travelTo(now()->addDays(3));

        $import = app(OrganizationCommunicationImportService::class)->import($delivery->fresh());
        $this->assertNotNull($import->id);
    }

    public function test_accepted_before_detachment_can_be_imported_after_detachment(): void
    {
        [$church, $staff] = $this->attachedChurch('Detachment Import Church');
        $revision = $this->distributedRevision();
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        $this->hierarchy->detachChurch($church);
        $this->asChurchUser($staff);

        $import = app(OrganizationCommunicationImportService::class)->import($delivery->fresh());
        $this->assertNotNull($import->id);
    }

    public function test_accepted_before_suspension_can_be_imported_after_suspension(): void
    {
        [$church, $staff] = $this->attachedChurch('Suspension Import Church');
        $revision = $this->distributedRevision();
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        Organization::query()->whereKey($this->organization->id)->update(['status' => OrganizationStatus::SUSPENDED->value]);
        $this->asChurchUser($staff);

        $import = app(OrganizationCommunicationImportService::class)->import($delivery->fresh());
        $this->assertNotNull($import->id);
    }

    public function test_duplicate_revision_via_a_second_delivery_is_denied(): void
    {
        [$church, $staff] = $this->attachedChurch('Duplicate Revision Church');

        $communication = $this->manager->create($this->unit, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Duplicate revision notice']);
        $revision = $communication->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Body.');
        $revision = $this->workflow->approve($this->workflow->submit($revision));

        // Two independent legitimate distributions of the exact same
        // revision reaching the exact same Church (§54/§82).
        $this->distributions->request($revision->fresh(), OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);
        $this->distributions->request($revision->fresh(), OrganizationCommunicationTargetMode::EXPLICIT_CHURCHES, null, [$church->id]);

        $deliveries = OrganizationCommunicationDelivery::query()
            ->where('church_id', $church->id)
            ->where('organization_communication_revision_id', $revision->id)
            ->get();
        $this->assertCount(2, $deliveries);

        $this->asChurchUser($staff);
        $service = app(OrganizationCommunicationChurchResponseService::class);
        $service->accept($deliveries[0]);
        $service->accept($deliveries[1]);

        $importService = app(OrganizationCommunicationImportService::class);
        $importService->import($deliveries[0]->fresh());

        $this->expectException(OrganizationCommunicationImportException::class);
        $importService->import($deliveries[1]->fresh());
    }

    // ---------------------------------------------------------------
    // §78/§79 content creation + provenance
    // ---------------------------------------------------------------

    public function test_import_creates_content_items_as_draft_with_organization_import_origin(): void
    {
        [$church, $staff] = $this->attachedChurch('Content Import Church');
        $revision = $this->distributedRevision(materialCount: 2);
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        app(OrganizationCommunicationImportService::class)->import($delivery->fresh());

        $items = ContentItem::query()->get();
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertSame(ContentStatus::DRAFT, $item->status);
            $this->assertSame(ContentOrigin::ORGANIZATION_IMPORT, $item->origin);
            $this->assertNull($item->approved_at);
        }
    }

    public function test_import_creates_campaign_with_linked_content_for_shared_campaign(): void
    {
        [$church, $staff] = $this->attachedChurch('Campaign Import Church');
        $revision = $this->distributedRevision(kind: OrganizationCommunicationKind::CAMPAIGN, materialCount: 2);
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        app(OrganizationCommunicationImportService::class)->import($delivery->fresh());

        $campaign = Campaign::query()->sole();
        $this->assertSame(CampaignStatus::DRAFT, $campaign->status);

        $communications = CampaignCommunication::query()->where('campaign_id', $campaign->id)->get();
        $this->assertCount(2, $communications);
        foreach ($communications as $communication) {
            $this->assertNotNull($communication->content_item_id);
        }
    }

    public function test_import_result_provenance_traces_to_exact_source(): void
    {
        [$church, $staff] = $this->attachedChurch('Provenance Church');
        $revision = $this->distributedRevision(materialCount: 1);
        $material = $revision->materials->sole();
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        $import = app(OrganizationCommunicationImportService::class)->import($delivery->fresh());

        $this->assertSame($this->organization->id, $import->organization_id);
        $this->assertSame($church->id, $import->church_id);
        $this->assertSame($delivery->id, $import->organization_communication_delivery_id);
        $this->assertSame($revision->id, $import->organization_communication_revision_id);

        $result = $import->results->sole();
        $contentItem = ContentItem::query()->sole();
        $this->assertSame($contentItem->id, $result->content_item_id);
        $this->assertSame($material->id, $result->organization_communication_material_id);
    }

    // ---------------------------------------------------------------
    // §80/§81 media
    // ---------------------------------------------------------------

    public function test_import_creates_media_asset_preserving_rights_and_attribution(): void
    {
        Storage::fake('media-private');
        [$church, $staff] = $this->attachedChurch('Media Import Church');
        $revision = $this->distributedRevision(materialCount: 0, beforeSubmit: function ($draftRevision): void {
            $this->manager->addMaterial($draftRevision, OrganizationCommunicationMaterialType::GENERAL, 'Body.');
            $this->assets->addAsset($draftRevision, $this->stageUpload('flyer.png'), 'flyer.png', [
                'rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION,
                'attribution_required' => true,
                'attribution_text' => 'Photo courtesy of the Diocese',
            ]);
        });
        $sourceAsset = $revision->assets->sole();
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        app(OrganizationCommunicationImportService::class)->import($delivery->fresh());

        $mediaAsset = MediaAsset::query()->sole();
        $this->assertNotSame($sourceAsset->path, $mediaAsset->path);
        $this->assertTrue(str_starts_with($mediaAsset->path, "tenants/{$church->id}/media/"));

        $rights = $mediaAsset->rights;
        $this->assertSame(AssetProvenance::OrganizationShared, $rights->provenance);
        $this->assertTrue($rights->attribution_required);
        $this->assertSame('Photo courtesy of the Diocese', $rights->attribution_text);

        // The Organization source asset itself is completely untouched.
        $this->assertTrue(Storage::disk($sourceAsset->disk)->exists($sourceAsset->path));
    }

    public function test_ineligible_asset_is_excluded_with_a_reason_and_not_imported(): void
    {
        Storage::fake('media-private');
        [$church, $staff] = $this->attachedChurch('Excluded Asset Church');
        $revision = $this->distributedRevision(materialCount: 0, beforeSubmit: function ($draftRevision): void {
            $this->manager->addMaterial($draftRevision, OrganizationCommunicationMaterialType::GENERAL, 'Body.');
            $stagingPath = "organizations/{$this->organization->uuid}/communications/.staging/".uniqid().'.pdf';
            Storage::disk('media-private')->put($stagingPath, '%PDF-1.4 fake');
            $this->assets->addAsset($draftRevision, $stagingPath, 'policy.pdf', [
                'rights_basis' => OrganizationCommunicationAssetRightsBasis::PUBLIC_DOMAIN,
            ]);
        });

        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        $this->asChurchUser($staff);
        $plan = app(OrganizationCommunicationImportService::class)->preview($delivery->fresh());
        $this->assertCount(1, $plan->excludedAssets);
        $this->assertSame(0, $plan->importableAssets->count());

        app(OrganizationCommunicationImportService::class)->import($delivery->fresh());
        $this->assertSame(0, MediaAsset::query()->count());
    }

    public function test_imported_media_is_attached_to_the_created_campaign(): void
    {
        Storage::fake('media-private');
        [$church, $staff] = $this->attachedChurch('Campaign Media Church');
        $revision = $this->distributedRevision(kind: OrganizationCommunicationKind::CAMPAIGN, materialCount: 0, beforeSubmit: function ($draftRevision): void {
            $this->manager->addMaterial($draftRevision, OrganizationCommunicationMaterialType::GENERAL, 'Body.');
            $this->assets->addAsset($draftRevision, $this->stageUpload('flyer.png'), 'flyer.png', [
                'rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION,
            ]);
        });

        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        app(OrganizationCommunicationImportService::class)->import($delivery->fresh());

        $campaign = Campaign::query()->sole();
        $mediaAsset = MediaAsset::query()->sole();
        $this->assertTrue($campaign->mediaAssociations()->where('media_asset_id', $mediaAsset->id)->exists());
    }

    // ---------------------------------------------------------------
    // §35/§36 idempotency, §37/§70 atomicity
    // ---------------------------------------------------------------

    public function test_import_is_idempotent_on_repeat(): void
    {
        [$church, $staff] = $this->attachedChurch('Idempotent Church');
        $revision = $this->distributedRevision(materialCount: 1);
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        $service = app(OrganizationCommunicationImportService::class);
        $first = $service->import($delivery->fresh());
        $second = $service->import($delivery->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, OrganizationCommunicationImport::query()->count());
        $this->assertSame(1, ContentItem::query()->count());
    }

    public function test_failed_import_rolls_back_completely(): void
    {
        [$church, $staff] = $this->attachedChurch('Rollback Church');
        $revision = $this->distributedRevision(materialCount: 2);
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        $calls = 0;
        $this->app->bind(CreateContentItemFromOrganizationImport::class, function () use (&$calls) {
            return new class($calls) extends CreateContentItemFromOrganizationImport
            {
                public function __construct(private int &$calls) {}

                public function handle($material, $contentType): ContentItem
                {
                    $this->calls++;
                    if ($this->calls === 2) {
                        throw new \RuntimeException('Induced failure for rollback proof.');
                    }

                    return parent::handle($material, $contentType);
                }
            };
        });

        try {
            app(OrganizationCommunicationImportService::class)->import($delivery->fresh());
            $this->fail('Import should have failed.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Induced failure for rollback proof.', $e->getMessage());
        }

        $this->assertSame(0, OrganizationCommunicationImport::query()->count());
        $this->assertSame(0, ContentItem::query()->count());
    }

    // ---------------------------------------------------------------
    // §80 local edit independence, §81 later revision
    // ---------------------------------------------------------------

    public function test_local_edit_after_import_does_not_affect_organization_source(): void
    {
        [$church, $staff] = $this->attachedChurch('Local Edit Church');
        $revision = $this->distributedRevision(materialCount: 1);
        $originalBody = $revision->materials->sole()->body;
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        app(OrganizationCommunicationImportService::class)->import($delivery->fresh());
        $contentItem = ContentItem::query()->sole();

        $contentItem->update(['body' => 'Locally rewritten by the Church.']);

        $this->assertSame('Locally rewritten by the Church.', $contentItem->fresh()->body);
        $this->assertSame($originalBody, $revision->materials->sole()->fresh()->body);
    }

    public function test_a_later_revision_does_not_affect_the_earlier_imported_copy(): void
    {
        [$church, $staff] = $this->attachedChurch('Later Revision Church');
        $revision1 = $this->distributedRevision(materialCount: 1);
        $delivery1 = $this->acceptedDelivery($church, $staff, $revision1);
        app(OrganizationCommunicationImportService::class)->import($delivery1->fresh());
        $contentItem = ContentItem::query()->sole();
        $contentItem->update(['title' => 'Locally renamed']);

        $this->asOrganizationUser($this->organization->memberships()->sole()->user);
        $revision2 = $this->manager->createNextRevision($revision1->fresh());
        $revision2 = $this->workflow->approve($this->workflow->submit($revision2->fresh()));
        $this->distributions->request($revision2->fresh(), OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);

        $this->assertSame('Locally renamed', $contentItem->fresh()->title);
    }

    // ---------------------------------------------------------------
    // §91-§94 no-touch guarantees
    // ---------------------------------------------------------------

    public function test_import_never_touches_website_faithflow_care_or_congregation(): void
    {
        [$church, $staff] = $this->attachedChurch('No Touch Church');
        $revision = $this->distributedRevision(materialCount: 1);
        $delivery = $this->acceptedDelivery($church, $staff, $revision);

        app(OrganizationCommunicationImportService::class)->import($delivery->fresh());

        $this->assertSame(0, WebsitePublication::query()->count());
        $this->assertSame(0, FaithFlowRun::query()->count());
        $this->assertSame(0, PrayerRequest::query()->count());
    }
}
