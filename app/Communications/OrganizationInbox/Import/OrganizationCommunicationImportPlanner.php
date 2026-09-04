<?php

namespace App\Communications\OrganizationInbox\Import;

use App\Enums\Capability;
use App\Enums\ContentType;
use App\Enums\OrganizationCommunicationAdaptationPolicy;
use App\Enums\OrganizationCommunicationKind;
use App\Filament\Support\MediaSelectField;
use App\Models\ChurchMembership;
use App\Models\OrganizationCommunicationAsset;
use App\Models\OrganizationCommunicationDelivery;
use App\Models\OrganizationCommunicationImport;
use Illuminate\Support\Collection;

/**
 * K-ORG-COMMS-001E §43 — builds the one deterministic
 * `OrganizationCommunicationImportPlan` that both the Inbox preview UI
 * and `OrganizationCommunicationImportService::import()` use. Performs
 * no mutation; purely a read/decision function.
 */
class OrganizationCommunicationImportPlanner
{
    public function plan(OrganizationCommunicationDelivery $delivery, ?ChurchMembership $membership): OrganizationCommunicationImportPlan
    {
        $revision = $delivery->revision;
        $communication = $revision?->communication;

        $eligible = true;
        $ineligibleReason = null;

        if ($delivery->derivedResponseState() !== 'accepted') {
            $eligible = false;
            $ineligibleReason = 'Only an Accepted communication can be imported.';
        } elseif ($revision?->adaptation_policy === OrganizationCommunicationAdaptationPolicy::REFERENCE_ONLY) {
            $eligible = false;
            $ineligibleReason = 'This communication was shared as reference material. You can review it here, but it cannot be imported directly into your Church workspace.';
        }

        $existingImport = OrganizationCommunicationImport::query()
            ->where('organization_communication_delivery_id', $delivery->id)
            ->first();

        $createsCampaign = $communication?->kind === OrganizationCommunicationKind::CAMPAIGN;

        $materialPlans = collect();
        foreach ($revision?->materials ?? [] as $material) {
            $contentType = ContentType::tryFrom($material->type->value);

            if ($contentType !== null) {
                $materialPlans->push(['material' => $material, 'contentType' => $contentType]);
            }
        }

        [$importableAssets, $excludedAssets] = $this->partitionAssets($revision?->assets ?? collect());

        $requiredCapabilities = [];
        if ($materialPlans->isNotEmpty()) {
            $requiredCapabilities[] = Capability::ContentManage;
        }
        if ($createsCampaign) {
            $requiredCapabilities[] = Capability::CampaignsManage;
        }
        if ($importableAssets->isNotEmpty()) {
            $requiredCapabilities[] = Capability::MediaManage;
        }

        $missingCapabilities = array_values(array_filter(
            $requiredCapabilities,
            fn (Capability $capability): bool => ! ($membership?->hasCapability($capability) ?? false),
        ));

        return new OrganizationCommunicationImportPlan(
            eligible: $eligible,
            ineligibleReason: $ineligibleReason,
            alreadyImported: $existingImport !== null,
            existingImport: $existingImport,
            createsCampaign: $createsCampaign,
            materialPlans: $materialPlans,
            importableAssets: $importableAssets,
            excludedAssets: $excludedAssets,
            requiredCapabilities: $requiredCapabilities,
            missingCapabilities: $missingCapabilities,
        );
    }

    /**
     * K-ORG-COMMS-001E §20/§38/§72 — an asset that cannot faithfully
     * satisfy Church Media's existing rights/format/size gate is
     * excluded here, with a truthful reason, rather than the gate being
     * weakened to admit it.
     *
     * @param  Collection<int, OrganizationCommunicationAsset>  $assets
     * @return array{0: Collection<int, OrganizationCommunicationAsset>, 1: Collection<int, array{asset: OrganizationCommunicationAsset, reason: string}>}
     */
    private function partitionAssets(Collection $assets): array
    {
        $importable = collect();
        $excluded = collect();

        foreach ($assets as $asset) {
            if (! in_array($asset->mime_type, MediaSelectField::ACCEPTED_MIME_TYPES, true)) {
                $excluded->push(['asset' => $asset, 'reason' => 'Church Media only accepts JPEG, PNG, or WebP images.']);

                continue;
            }

            if ($asset->size > MediaSelectField::MAX_UPLOAD_SIZE_KB * 1024) {
                $excluded->push(['asset' => $asset, 'reason' => 'This file is larger than Church Media\'s 10 MB limit.']);

                continue;
            }

            $importable->push($asset);
        }

        return [$importable, $excluded];
    }
}
