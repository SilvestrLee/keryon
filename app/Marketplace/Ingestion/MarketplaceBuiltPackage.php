<?php

namespace App\Marketplace\Ingestion;

final readonly class MarketplaceBuiltPackage
{
    public function __construct(
        public string $bytes,
        public string $filename,
        public string $sha256,
        public int $size,
    ) {}
}
