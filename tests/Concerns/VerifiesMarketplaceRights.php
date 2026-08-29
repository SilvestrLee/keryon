<?php

namespace Tests\Concerns;

use App\Marketplace\ReviewMarketplaceSourceRights;
use App\Models\MarketplaceSourceVersion;

trait VerifiesMarketplaceRights
{
    private function verifyMarketplaceRights(MarketplaceSourceVersion $source, array $overrides = []): MarketplaceSourceVersion
    {
        return app(ReviewMarketplaceSourceRights::class)->verify($source, array_replace_recursive([
            'creator_name' => 'Keryon Test Studio',
            'rightsholder_name' => 'Keryon Test Studio',
            'license_reference' => 'test-marketplace-license-v1',
            'licensing_metadata' => [
                'status' => 'verified',
                'redistribution_authority' => 'verified',
            ],
            'font_metadata' => [
                'declaration_status' => 'publisher_declared',
                'fonts' => [],
                'font_files_bundled' => false,
            ],
            'source_provenance' => 'Synthetic test source created by Keryon tests.',
            'rights_evidence_reference' => 'test-evidence:marketplace-source',
        ], $overrides), 'test-platform-operator');
    }
}
