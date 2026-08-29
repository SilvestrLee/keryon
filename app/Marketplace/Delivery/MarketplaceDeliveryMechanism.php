<?php

namespace App\Marketplace\Delivery;

use App\Models\MarketplaceSourceVersion;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface MarketplaceDeliveryMechanism
{
    public function response(MarketplaceSourceVersion $source): StreamedResponse;
}
