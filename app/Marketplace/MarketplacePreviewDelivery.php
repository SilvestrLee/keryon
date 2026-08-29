<?php

namespace App\Marketplace;

use App\Enums\MarketplacePublicationStatus;
use App\Models\MarketplacePreview;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketplacePreviewDelivery
{
    public function response(MarketplacePreview $preview): StreamedResponse
    {
        $this->authorize($preview);

        return Storage::disk($preview->disk)->response(
            $preview->getRawOriginal('storage_key'),
            null,
            [
                'Content-Type' => $preview->mime_type,
                'Cache-Control' => 'private, max-age=300',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ],
        );
    }

    public function dataUri(MarketplacePreview $preview): string
    {
        $this->authorize($preview);
        $bytes = Storage::disk($preview->disk)->get($preview->getRawOriginal('storage_key'));

        if (! is_string($bytes) || ! hash_equals($preview->sha256, hash('sha256', $bytes))) {
            throw ValidationException::withMessages(['marketplace' => 'This Marketplace preview is not available.']);
        }

        return 'data:'.$preview->mime_type.';base64,'.base64_encode($bytes);
    }

    private function authorize(MarketplacePreview $preview): void
    {
        Gate::authorize('view', $preview->item);

        if ($preview->item->trashed()
            || $preview->item->publication_status !== MarketplacePublicationStatus::PUBLISHED
            || $preview->item->published_at === null
            || $preview->item->currentSourceVersion() === null) {
            throw ValidationException::withMessages(['marketplace' => 'This Marketplace preview is not available.']);
        }
    }
}
