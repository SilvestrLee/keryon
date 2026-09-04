<?php

namespace Tests\Feature\Communications;

use App\Communications\OrganizationInbox\ChurchOrganizationCommunicationQuery;
use App\Communications\OrganizationInbox\Exceptions\OrganizationCommunicationResponseException;
use App\Communications\OrganizationInbox\OrganizationCommunicationChurchResponseService;
use App\Enums\Capability;
use App\Enums\ChurchRole;
use App\Enums\OrganizationCommunicationAssetRightsBasis;
use App\Enums\OrganizationCommunicationDeclineReasonCode;
use App\Enums\OrganizationCommunicationKind;
use App\Enums\OrganizationCommunicationMaterialType;
use App\Enums\OrganizationCommunicationTargetMode;
use App\Enums\OrganizationStatus;
use App\Filament\Pages\OrganizationInbox as OrganizationInboxPage;
use App\Filament\Pages\OrganizationInboxDetail;
use App\Models\Campaign;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ContentItem;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\OrganizationCommunicationDelivery;
use App\Models\OrganizationCommunicationRevision;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitType;
use App\Models\User;
use App\Organizations\Communications\Distribution\OrganizationCommunicationDistributionManager;
use App\Organizations\Communications\OrganizationCommunicationAssetManager;
use App\Organizations\Communications\OrganizationCommunicationManager;
use App\Organizations\Communications\OrganizationCommunicationWorkflow;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationIdentityService;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-ORG-COMMS-001D §55-§64 — the Church Inbox & Response test matrix.
 * "ACCEPT != IMPORT": every accept/decline assertion here also confirms
 * no ContentItem/Campaign/MediaAsset is ever created as a side effect.
 */
class OrganizationInboxTest extends TestCase
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

        $this->organization = $this->hierarchy->createOrganization('Diocese Network', 'diocese-network');
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

    /** @return array{Church, User} the Church and its Administrator+Communications staff user */
    private function attachedChurch(string $name, array $roles = [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS]): array
    {
        $church = Church::factory()->create(['name' => $name]);
        $staff = User::factory()->create();
        ChurchMembership::createPrimary($church, $staff, $roles);
        $pending = $this->hierarchy->attachChurch($this->organization, $this->unit, $church);
        $this->hierarchy->acceptAttachment($pending, $staff);

        return [$church->fresh(), $staff];
    }

    /** Creates, submits, approves, and distributes a communication to the whole governing scope. */
    private function distributedRevision(array $overrides = [], OrganizationCommunicationKind $kind = OrganizationCommunicationKind::COMMUNICATION, ?callable $beforeSubmit = null): OrganizationCommunicationRevision
    {
        $communication = $this->manager->create($this->unit, $kind, array_merge(['title' => 'Fall gathering notice'], $overrides));
        $revision = $communication->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Please share this with your congregation.');

        if ($beforeSubmit !== null) {
            $beforeSubmit($revision);
        }

        $revision = $this->workflow->approve($this->workflow->submit($revision->fresh()));

        $this->distributions->request($revision->fresh(), OrganizationCommunicationTargetMode::GOVERNING_SCOPE, null, null);

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

    // ---------------------------------------------------------------
    // §55-56 authorization
    // ---------------------------------------------------------------

    public function test_administrator_and_communications_roles_have_view_and_respond_capability(): void
    {
        $this->assertTrue(collect(ChurchRole::ADMINISTRATOR->capabilities())->contains(Capability::OrganizationCommunicationsView));
        $this->assertTrue(collect(ChurchRole::ADMINISTRATOR->capabilities())->contains(Capability::OrganizationCommunicationsRespond));
        $this->assertTrue(collect(ChurchRole::COMMUNICATIONS->capabilities())->contains(Capability::OrganizationCommunicationsView));
        $this->assertTrue(collect(ChurchRole::COMMUNICATIONS->capabilities())->contains(Capability::OrganizationCommunicationsRespond));
        $this->assertFalse(collect(ChurchRole::CARE->capabilities())->contains(Capability::OrganizationCommunicationsView));
    }

    public function test_import_capability_is_granted_to_administrator_and_communications_only(): void
    {
        // K-ORG-COMMS-001D declared this capability unassigned;
        // K-ORG-COMMS-001E §6 activates it for Administrator and
        // Communications. Care never carries it.
        $this->assertTrue(collect(ChurchRole::ADMINISTRATOR->capabilities())->contains(Capability::OrganizationCommunicationsImport));
        $this->assertTrue(collect(ChurchRole::COMMUNICATIONS->capabilities())->contains(Capability::OrganizationCommunicationsImport));
        $this->assertFalse(collect(ChurchRole::CARE->capabilities())->contains(Capability::OrganizationCommunicationsImport));
    }

    public function test_care_role_cannot_access_the_inbox(): void
    {
        [$church] = $this->attachedChurch('Care Only Church');
        $careUser = User::factory()->create();
        $careMembership = $careUser->memberships()->create(['church_id' => $church->id, 'status' => 'active', 'joined_at' => now()]);
        $careMembership->assignRoles([ChurchRole::CARE]);
        $this->asChurchUser($careUser);

        $this->assertFalse(OrganizationInboxPage::canAccess());
    }

    public function test_organization_membership_cannot_open_the_church_inbox(): void
    {
        // Still acting as the Organization admin from setUp — no Church
        // TenantContext at all.
        $this->assertNull(app(TenantContext::class)->currentMembership());
        $this->assertFalse(OrganizationInboxPage::canAccess());
    }

    public function test_a_church_cannot_view_or_respond_to_another_churchs_delivery(): void
    {
        [$churchA] = $this->attachedChurch('Church A');
        [$churchB, $staffB] = $this->attachedChurch('Church B');
        $revision = $this->distributedRevision();
        $deliveryA = $this->deliveryFor($churchA, $revision);

        $this->asChurchUser($staffB);
        $this->assertFalse(auth()->user()->can('view', $deliveryA));
        $this->assertFalse(auth()->user()->can('respond', $deliveryA));

        // Church B's scoped query never finds Church A's delivery at all
        // — a factual "not found", not a leaked "forbidden" that would
        // confirm the record's existence.
        $this->expectException(ModelNotFoundException::class);
        app(ChurchOrganizationCommunicationQuery::class)->findByUuid($deliveryA->uuid);
    }

    // ---------------------------------------------------------------
    // §57 inbox scoping/filters
    // ---------------------------------------------------------------

    public function test_inbox_only_shows_the_current_churchs_deliveries(): void
    {
        [$churchA, $staffA] = $this->attachedChurch('Visible Church');
        [$churchB, $staffB] = $this->attachedChurch('Invisible Church');
        $revision = $this->distributedRevision();
        $deliveryA = $this->deliveryFor($churchA, $revision);
        $deliveryB = $this->deliveryFor($churchB, $revision);

        $this->asChurchUser($staffA);
        Livewire::test(OrganizationInboxPage::class)->assertSee('Fall gathering notice');
        $resultsA = app(ChurchOrganizationCommunicationQuery::class)->paginate();
        $this->assertSame([$deliveryA->uuid], $resultsA->pluck('uuid')->all());

        $this->asChurchUser($staffB);
        $resultsB = app(ChurchOrganizationCommunicationQuery::class)->paginate();
        $this->assertSame([$deliveryB->uuid], $resultsB->pluck('uuid')->all());
    }

    public function test_inbox_state_filter_separates_available_and_accepted(): void
    {
        [$church, $staff] = $this->attachedChurch('Filtering Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->asChurchUser($staff);
        app(OrganizationCommunicationChurchResponseService::class)->accept($delivery);

        $available = app(ChurchOrganizationCommunicationQuery::class)->paginate(ChurchOrganizationCommunicationQuery::STATE_AVAILABLE);
        $accepted = app(ChurchOrganizationCommunicationQuery::class)->paginate(ChurchOrganizationCommunicationQuery::STATE_ACCEPTED);

        $this->assertSame(0, $available->total());
        $this->assertSame(1, $accepted->total());
    }

    // ---------------------------------------------------------------
    // §58 resources / §59 assets
    // ---------------------------------------------------------------

    public function test_detail_page_renders_materials_as_safe_markdown_without_raw_html(): void
    {
        [$church, $staff] = $this->attachedChurch('Markdown Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->asChurchUser($staff);
        $material = $delivery->revision->materials->sole();
        $material->body = "<script>alert(1)</script>\n\n**bold**";

        $page = new OrganizationInboxDetail;
        $html = $page->materialBodyHtml($material);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    public function test_church_asset_route_requires_matching_delivery_capability_and_revision(): void
    {
        Storage::fake('media-private');
        $asset = null;

        [$churchA, $staffA] = $this->attachedChurch('Asset Church A');
        [, $staffB] = $this->attachedChurch('Asset Church B');

        $revision = $this->distributedRevision([], OrganizationCommunicationKind::COMMUNICATION, function ($draftRevision) use (&$asset): void {
            $asset = $this->assets->addAsset($draftRevision, $this->stageUpload('flyer.png'), 'flyer.png', [
                'rights_basis' => OrganizationCommunicationAssetRightsBasis::OWNED_BY_ORGANIZATION,
            ]);
        });

        $deliveryA = $this->deliveryFor($churchA, $revision);

        $this->asChurchUser($staffA);
        $this->assertTrue(auth()->user()->can('viewAsset', [$deliveryA, $asset]));

        $this->asChurchUser($staffB);
        $this->assertFalse(auth()->user()->can('viewAsset', [$deliveryA, $asset]));
    }

    // ---------------------------------------------------------------
    // §60 accept
    // ---------------------------------------------------------------

    public function test_accept_records_evidence_and_never_creates_local_records(): void
    {
        [$church, $staff] = $this->attachedChurch('Accepting Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->asChurchUser($staff);
        $membership = app(TenantContext::class)->currentMembership();
        $result = app(OrganizationCommunicationChurchResponseService::class)->accept($delivery);

        $this->assertNotNull($result->accepted_at);
        $this->assertNull($result->declined_at);
        $this->assertSame($membership->id, $result->responded_by_church_membership_id);
        $this->assertSame('accepted', $result->derivedResponseState());

        $this->assertSame(0, ContentItem::query()->count());
        $this->assertSame(0, Campaign::query()->count());
        $this->assertSame(0, MediaAsset::query()->count());
    }

    public function test_accept_is_idempotent_on_repeat(): void
    {
        [$church, $staff] = $this->attachedChurch('Repeat Accept Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->asChurchUser($staff);
        $service = app(OrganizationCommunicationChurchResponseService::class);
        $first = $service->accept($delivery);
        $second = $service->accept($delivery->fresh());

        $this->assertSame($first->accepted_at->getTimestamp(), $second->accepted_at->getTimestamp());
        $this->assertSame(1, OrganizationCommunicationDelivery::query()->whereNotNull('accepted_at')->count());
    }

    public function test_decline_after_accept_is_denied(): void
    {
        [$church, $staff] = $this->attachedChurch('Cross Response Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->asChurchUser($staff);
        $service = app(OrganizationCommunicationChurchResponseService::class);
        $service->accept($delivery);

        $this->expectException(OrganizationCommunicationResponseException::class);
        $service->decline($delivery->fresh());
    }

    public function test_respond_is_denied_without_the_respond_capability(): void
    {
        [$church] = $this->attachedChurch('View Only Church');
        $revision = $this->distributedRevision();
        $viewOnlyUser = User::factory()->create();
        // Care role only — grants neither view nor respond.
        $viewOnlyMembership = $viewOnlyUser->memberships()->create(['church_id' => $church->id, 'status' => 'active', 'joined_at' => now()]);
        $viewOnlyMembership->assignRoles([ChurchRole::CARE]);
        $delivery = $this->deliveryFor($church, $revision);

        $this->asChurchUser($viewOnlyUser);
        $this->assertFalse(auth()->user()->can('respond', $delivery));

        $this->expectException(AuthorizationException::class);
        app(OrganizationCommunicationChurchResponseService::class)->accept($delivery);
    }

    // ---------------------------------------------------------------
    // §61 decline
    // ---------------------------------------------------------------

    public function test_decline_records_optional_reason_code(): void
    {
        [$church, $staff] = $this->attachedChurch('Declining Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->asChurchUser($staff);
        $result = app(OrganizationCommunicationChurchResponseService::class)->decline($delivery, OrganizationCommunicationDeclineReasonCode::TIMING_NOT_SUITABLE);

        $this->assertNotNull($result->declined_at);
        $this->assertNull($result->accepted_at);
        $this->assertSame(OrganizationCommunicationDeclineReasonCode::TIMING_NOT_SUITABLE, $result->decline_reason_code);
        $this->assertSame('declined', $result->derivedResponseState());
    }

    public function test_decline_is_idempotent_on_repeat(): void
    {
        [$church, $staff] = $this->attachedChurch('Repeat Decline Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->asChurchUser($staff);
        $service = app(OrganizationCommunicationChurchResponseService::class);
        $service->decline($delivery);
        $second = $service->decline($delivery->fresh(), OrganizationCommunicationDeclineReasonCode::OTHER);

        // The second call converges on the already-declined row rather
        // than overwriting the recorded reason.
        $this->assertNull($second->decline_reason_code);
    }

    public function test_accept_after_decline_is_denied(): void
    {
        [$church, $staff] = $this->attachedChurch('Cross Response Church Two');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->asChurchUser($staff);
        $service = app(OrganizationCommunicationChurchResponseService::class);
        $service->decline($delivery);

        $this->expectException(OrganizationCommunicationResponseException::class);
        $service->accept($delivery->fresh());
    }

    // ---------------------------------------------------------------
    // §62 expiry
    // ---------------------------------------------------------------

    public function test_expired_unresponded_delivery_cannot_be_accepted_or_declined(): void
    {
        [$church, $staff] = $this->attachedChurch('Expired Church');
        $revision = $this->distributedRevision(['available_until' => now()->subDay()]);
        $delivery = $this->deliveryFor($church, $revision);

        $this->assertSame('expired', $delivery->derivedResponseState());

        $this->asChurchUser($staff);
        $service = app(OrganizationCommunicationChurchResponseService::class);

        try {
            $service->accept($delivery);
            $this->fail('Expired delivery accepted.');
        } catch (OrganizationCommunicationResponseException $e) {
            $this->assertStringContainsString('closed', $e->getMessage());
        }
    }

    public function test_accepted_before_expiry_remains_accepted_and_is_never_flipped(): void
    {
        [$church, $staff] = $this->attachedChurch('Grace Period Church');
        $revision = $this->distributedRevision(['available_until' => now()->addDay()]);
        $delivery = $this->deliveryFor($church, $revision);

        $this->asChurchUser($staff);
        app(OrganizationCommunicationChurchResponseService::class)->accept($delivery);

        $this->travelTo(now()->addDays(2));

        $this->assertSame('accepted', $delivery->fresh()->derivedResponseState());
    }

    // ---------------------------------------------------------------
    // §63 detachment / organization suspension
    // ---------------------------------------------------------------

    public function test_detached_church_cannot_record_a_new_response_but_history_remains(): void
    {
        [$church, $staff] = $this->attachedChurch('Detaching Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->hierarchy->detachChurch($church);

        $this->asChurchUser($staff);
        // The Church has no Church-panel context restriction from
        // detachment itself (TenantContext still resolves the
        // membership); the response service is what must deny it.
        app(TenantContext::class)->forgetResolved();

        $service = app(OrganizationCommunicationChurchResponseService::class);

        try {
            $service->accept($delivery);
            $this->fail('Detached Church recorded a new response.');
        } catch (OrganizationCommunicationResponseException $e) {
            $this->assertStringContainsString('no longer connected', $e->getMessage());
        }

        // History remains visible.
        $found = app(ChurchOrganizationCommunicationQuery::class)->findByUuid($delivery->uuid);
        $this->assertSame($delivery->id, $found->id);
    }

    public function test_accepted_before_detachment_remains_accepted(): void
    {
        [$church, $staff] = $this->attachedChurch('Accept Then Detach Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->asChurchUser($staff);
        app(OrganizationCommunicationChurchResponseService::class)->accept($delivery);

        $this->hierarchy->detachChurch($church);

        $this->assertSame('accepted', $delivery->fresh()->derivedResponseState());
    }

    public function test_suspended_organization_blocks_a_new_response(): void
    {
        [$church, $staff] = $this->attachedChurch('Suspended Org Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        Organization::query()->whereKey($this->organization->id)->update(['status' => OrganizationStatus::SUSPENDED->value]);

        $this->asChurchUser($staff);
        $service = app(OrganizationCommunicationChurchResponseService::class);

        try {
            $service->decline($delivery);
            $this->fail('Suspended Organization allowed a new response.');
        } catch (OrganizationCommunicationResponseException $e) {
            $this->assertStringContainsString('not currently active', $e->getMessage());
        }
    }

    public function test_accepted_before_suspension_remains_visible_and_accepted(): void
    {
        [$church, $staff] = $this->attachedChurch('Accept Then Suspend Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->asChurchUser($staff);
        app(OrganizationCommunicationChurchResponseService::class)->accept($delivery);

        Organization::query()->whereKey($this->organization->id)->update(['status' => OrganizationStatus::SUSPENDED->value]);

        $found = app(ChurchOrganizationCommunicationQuery::class)->findByUuid($delivery->uuid);
        $this->assertSame('accepted', $found->derivedResponseState());
    }

    // ---------------------------------------------------------------
    // §48 immutability guard (defense in depth at the model layer)
    // ---------------------------------------------------------------

    public function test_model_rejects_direct_mutation_of_non_response_fields(): void
    {
        [$church] = $this->attachedChurch('Immutable Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->expectException(\LogicException::class);
        $delivery->forceFill(['available_at' => now()->addWeek()])->save();
    }

    public function test_model_rejects_both_accepted_and_declined_being_set_together(): void
    {
        [$church] = $this->attachedChurch('Both Set Church');
        $revision = $this->distributedRevision();
        $delivery = $this->deliveryFor($church, $revision);

        $this->expectException(\LogicException::class);
        $delivery->forceFill(['accepted_at' => now(), 'declined_at' => now()])->save();
    }
}
