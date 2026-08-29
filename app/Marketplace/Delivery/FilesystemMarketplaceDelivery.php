<?php

namespace App\Marketplace\Delivery;

use App\Models\MarketplaceSourceVersion;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilesystemMarketplaceDelivery implements MarketplaceDeliveryMechanism
{
    public function response(MarketplaceSourceVersion $source): StreamedResponse
    {
        return Storage::disk($source->disk)->download(
            $source->getRawOriginal('storage_key'),
            basename($source->original_filename),
            [
                'Content-Type' => $source->mime_type,
                'Cache-Control' => 'private, no-store',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                'X-Content-SHA256' => $source->sha256,
            ],
        );
    }
}
