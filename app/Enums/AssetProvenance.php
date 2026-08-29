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
}
