<?php

namespace App\Marketplace;

use App\Enums\MarketplaceRightsStatus;
use App\Models\MarketplaceSourceVersion;

class MarketplaceRightsGate
{
    public function permits(MarketplaceSourceVersion $source): bool
    {
        if ($source->rights_status !== MarketplaceRightsStatus::VERIFIED
            || blank($source->creator_name)
            || blank($source->rightsholder_name)
            || blank($source->license_reference)
            || blank($source->source_provenance)
            || blank($source->rights_evidence_reference)
            || $source->rights_verified_by_type !== 'platform_operator'
            || blank($source->rights_verified_by_reference)
            || $source->rights_verified_at === null) {
            return false;
        }

        $licensing = $source->licensing_metadata ?? [];
        $fonts = $source->font_metadata ?? [];

        if (data_get($licensing, 'status') !== 'verified'
            || data_get($licensing, 'redistribution_authority') !== 'verified'
            || data_get($fonts, 'declaration_status') !== 'publisher_declared'
            || ! is_bool(data_get($fonts, 'font_files_bundled'))) {
            return false;
        }

        return data_get($fonts, 'font_files_bundled') === false
            || data_get($fonts, 'bundled_font_redistribution_cleared') === true;
    }
}
