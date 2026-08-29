<?php

namespace App\Marketplace\Ingestion;

use App\Models\MarketplaceItem;
use App\Models\MarketplaceSourceVersion;

final readonly class MarketplaceReferenceImportResult
{
    public function __construct(
        public MarketplaceItem $item,
        public MarketplaceSourceVersion $source,
        public MarketplacePsdInspection $inspection,
        public MarketplaceBuiltPackage $package,
        public bool $alreadyImported,
    ) {}
}
