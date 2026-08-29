<?php

namespace App\Marketplace\Entitlements;

use App\Enums\MarketplaceAcquisitionBasis;

final readonly class MarketplaceEntitlementDecision
{
    private function __construct(
        public bool $allowed,
        public ?MarketplaceAcquisitionBasis $basis,
        public ?string $reference,
        public ?string $failureCode,
    ) {}

    public static function allow(MarketplaceAcquisitionBasis $basis, ?string $reference = null): self
    {
        return new self(true, $basis, $reference, null);
    }

    public static function deny(string $failureCode): self
    {
        return new self(false, null, null, $failureCode);
    }
}
