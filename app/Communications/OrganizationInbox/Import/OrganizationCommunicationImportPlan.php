<?php

namespace App\Communications\OrganizationInbox\Import;

use App\Enums\Capability;
use App\Enums\ContentType;
use App\Models\OrganizationCommunicationAsset;
use App\Models\OrganizationCommunicationImport;
use App\Models\OrganizationCommunicationMaterial;
use Illuminate\Support\Collection;

/**
 * K-ORG-COMMS-001E §43 — the single deterministic plan both the Inbox
 * preview UI and the import execution use. Nothing in the executing
 * service ever decides something different from what this plan already
 * described — building the plan performs zero mutation.
 */
final class OrganizationCommunicationImportPlan
{
    /**
     * @param  Collection<int, array{material: OrganizationCommunicationMaterial, contentType: ContentType}>  $materialPlans
     * @param  Collection<int, OrganizationCommunicationAsset>  $importableAssets
     * @param  Collection<int, array{asset: OrganizationCommunicationAsset, reason: string}>  $excludedAssets
     * @param  list<Capability>  $requiredCapabilities
     * @param  list<Capability>  $missingCapabilities
     */
    public function __construct(
        public readonly bool $eligible,
        public readonly ?string $ineligibleReason,
        public readonly bool $alreadyImported,
        public readonly ?OrganizationCommunicationImport $existingImport,
        public readonly bool $createsCampaign,
        public readonly Collection $materialPlans,
        public readonly Collection $importableAssets,
        public readonly Collection $excludedAssets,
        public readonly array $requiredCapabilities,
        public readonly array $missingCapabilities,
    ) {}

    public function isAuthorized(): bool
    {
        return $this->missingCapabilities === [];
    }

    public function canExecute(): bool
    {
        return $this->eligible && ! $this->alreadyImported && $this->isAuthorized();
    }

    public function contentItemCount(): int
    {
        return $this->materialPlans->count();
    }

    public function assetCount(): int
    {
        return $this->importableAssets->count();
    }
}
