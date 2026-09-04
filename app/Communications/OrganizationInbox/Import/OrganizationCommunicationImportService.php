<?php

namespace App\Communications\OrganizationInbox\Import;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Campaigns\CampaignMediaManager;
use App\Communications\OrganizationInbox\Import\Exceptions\OrganizationCommunicationImportException;
use App\Enums\CommunicationChannel;
use App\Models\ChurchMembership;
use App\Models\OrganizationCommunicationDelivery;
use App\Models\OrganizationCommunicationImport;
use App\Models\OrganizationCommunicationImportResult;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * K-ORG-COMMS-001E §42 — the single canonical seam through which a
 * Church may ever import an Accepted Organization Communication
 * delivery into new, Church-owned records.
 *
 * "ORGANIZATION SOURCE ≠ CHURCH COPY": every record this creates goes
 * through its own domain's canonical creation boundary
 * (`CampaignManager`, `CampaignCommunicationManager`,
 * `CreateContentItemFromOrganizationImport`,
 * `ImportOrganizationCommunicationAsset`) — this service never mass-
 * assigns a Church model directly. Authorization is conjunctive (§44):
 * the base `organization_communications.import` capability from the
 * Policy, plus every destination capability the concrete plan actually
 * requires.
 *
 * Atomic (§37/§39): the whole import happens inside one
 * `DB::transaction()`, with the delivery row locked first so two
 * concurrent import attempts serialize instead of racing. A second call
 * against an already-imported delivery converges idempotently on the
 * existing `OrganizationCommunicationImport` row rather than erroring or
 * duplicating records (§35).
 */
class OrganizationCommunicationImportService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OrganizationCommunicationImportPlanner $planner,
    ) {}

    public function preview(OrganizationCommunicationDelivery $delivery): OrganizationCommunicationImportPlan
    {
        Gate::authorize('import', $delivery);
        $membership = $this->requireMembership();

        return $this->planner->plan($this->loadDelivery($delivery), $membership);
    }

    public function import(OrganizationCommunicationDelivery $delivery): OrganizationCommunicationImport
    {
        Gate::authorize('import', $delivery);
        $membership = $this->requireMembership();

        return DB::transaction(function () use ($delivery, $membership): OrganizationCommunicationImport {
            $locked = OrganizationCommunicationDelivery::query()
                ->whereKey($delivery->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency (§35/§36) — a second call against an
            // already-imported delivery converges on the existing event,
            // no duplicate records, no error.
            $existing = OrganizationCommunicationImport::query()
                ->where('organization_communication_delivery_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $plan = $this->planner->plan($this->loadDelivery($locked), $membership);

            if (! $plan->eligible) {
                throw $plan->ineligibleReason !== null && str_contains($plan->ineligibleReason, 'reference material')
                    ? OrganizationCommunicationImportException::referenceOnly()
                    : OrganizationCommunicationImportException::notAccepted();
            }

            if (! $plan->isAuthorized()) {
                throw OrganizationCommunicationImportException::missingDestinationCapability(
                    array_map(fn ($capability) => $capability->label(), $plan->missingCapabilities),
                );
            }

            // K-ORG-COMMS-001E §54/§82 — deny a second local copy of the
            // exact same source revision via a *different* delivery. This
            // is my recommendation for Product Office decision A — see
            // the K-ORG-COMMS-001E report.
            $duplicateRevisionImport = OrganizationCommunicationImport::query()
                ->where('church_id', $membership->church_id)
                ->where('organization_communication_revision_id', $locked->organization_communication_revision_id)
                ->exists();

            if ($duplicateRevisionImport) {
                throw OrganizationCommunicationImportException::revisionAlreadyImportedElsewhere();
            }

            return $this->execute($locked, $membership, $plan);
        });
    }

    private function execute(OrganizationCommunicationDelivery $delivery, ChurchMembership $membership, OrganizationCommunicationImportPlan $plan): OrganizationCommunicationImport
    {
        $import = new OrganizationCommunicationImport;
        $import->forceFill([
            'organization_id' => $delivery->organization_id,
            'church_id' => $membership->church_id,
            'organization_communication_delivery_id' => $delivery->id,
            'organization_communication_revision_id' => $delivery->organization_communication_revision_id,
            'imported_by_church_membership_id' => $membership->id,
        ])->save();

        $revision = $delivery->revision;
        $campaign = null;

        if ($plan->createsCampaign) {
            $campaign = app(CampaignManager::class)->create([
                'title' => $revision->title ?? 'Shared campaign',
                'purpose' => $revision->summary,
                'starts_on' => $revision->campaign_starts_on,
                'ends_on' => $revision->campaign_ends_on,
            ]);

            $this->recordResult($import, $membership->church_id, campaignId: $campaign->id);
        }

        foreach ($plan->materialPlans as $entry) {
            $material = $entry['material'];
            $contentItem = app(CreateContentItemFromOrganizationImport::class)->handle($material, $entry['contentType']);

            $this->recordResult($import, $membership->church_id, contentItemId: $contentItem->id, materialId: $material->id);

            if ($campaign !== null) {
                $communication = app(CampaignCommunicationManager::class)->add($campaign, [
                    'title' => $contentItem->title,
                    'channel' => CommunicationChannel::GENERAL,
                ]);
                app(CampaignCommunicationManager::class)->linkContentItem($communication, $contentItem);
            }
        }

        foreach ($plan->importableAssets as $asset) {
            $mediaAsset = app(ImportOrganizationCommunicationAsset::class)->handle(
                $asset,
                $membership->church_id,
                $asset->attribution_required,
                $asset->attribution_text,
            );

            $this->recordResult($import, $membership->church_id, mediaAssetId: $mediaAsset->id, assetId: $asset->id);

            // K-ORG-COMMS-001E §15/§49 — a Shared Campaign import gives
            // its imported media somewhere concrete to be found (the new
            // Campaign's own media list); a plain Communication import
            // has no Media browsing surface to attach to, so the asset
            // simply becomes selectable Church Media everywhere else.
            if ($campaign !== null) {
                app(CampaignMediaManager::class)->attach($campaign, $mediaAsset);
            }
        }

        return $import->fresh(['results']);
    }

    private function recordResult(
        OrganizationCommunicationImport $import,
        int $churchId,
        ?int $contentItemId = null,
        ?int $campaignId = null,
        ?int $mediaAssetId = null,
        ?int $materialId = null,
        ?int $assetId = null,
    ): OrganizationCommunicationImportResult {
        return OrganizationCommunicationImportResult::query()->forceCreate([
            'organization_communication_import_id' => $import->id,
            'church_id' => $churchId,
            'content_item_id' => $contentItemId,
            'campaign_id' => $campaignId,
            'media_asset_id' => $mediaAssetId,
            'organization_communication_material_id' => $materialId,
            'organization_communication_asset_id' => $assetId,
        ]);
    }

    private function loadDelivery(OrganizationCommunicationDelivery $delivery): OrganizationCommunicationDelivery
    {
        return $delivery->fresh(['revision.materials', 'revision.assets', 'revision.communication']);
    }

    private function requireMembership(): ChurchMembership
    {
        $membership = $this->tenantContext->currentMembership();

        abort_if($membership === null, 403);

        return $membership;
    }
}
