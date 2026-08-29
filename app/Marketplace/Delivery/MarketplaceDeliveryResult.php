<?php

namespace App\Marketplace\Delivery;

use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class MarketplaceDeliveryResult
{
    public function __construct(
        public int $downloadEventId,
        public string $sha256,
        public StreamedResponse $response,
    ) {}
}
