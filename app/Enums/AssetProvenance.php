<?php

namespace App\Enums;

enum AssetProvenance: string
{
    case ChurchDeclared = 'church_declared';
    case KeryonCreated = 'keryon_created';
    case KeryonCommissioned = 'keryon_commissioned';
    case LicensedThirdParty = 'licensed_third_party';
    case MarketplaceCreator = 'marketplace_creator';
    case AiGenerated = 'ai_generated';
    case Unknown = 'unknown';

    /**
     * K-ORG-COMMS-001E §20 — an asset imported from an Organization
     * Communication. Rights were declared by the Organization at
     * authoring time (`OrganizationCommunicationAsset::rights_basis`),
     * not by the receiving Church — semantically distinct from
     * `ChurchDeclared`, so its own case rather than a reuse.
     */
    case OrganizationShared = 'organization_shared';
}
