<?php

namespace App\PublicWebsite;

use App\Enums\MediaPublicConsumer;
use App\Enums\MediaRenditionState;
use App\Models\MediaPublicReference;
use App\Models\MediaRendition;
use App\Models\WebsitePublication;
use Illuminate\Support\Facades\Storage;

class WebsitePublicationHealth
{
    /** @return array{state: 'healthy'|'degraded', unavailable_usages: list<string>} */
    public function evaluate(WebsitePublication $publication): array
    {
        $unavailable = [];

        foreach ($publication->snapshot['public_media'] ?? [] as $usage => $uuid) {
            $rendition = MediaRendition::withoutGlobalScopes()->where('uuid', $uuid)->first();
            $hasReference = $rendition !== null && MediaPublicReference::withoutGlobalScopes()
                ->where('consumer_type', MediaPublicConsumer::WebsitePublication->value)
                ->where('consumer_id', $publication->id)
                ->where('media_rendition_id', $rendition->id)
                ->where('usage_key', $usage)
                ->whereNull('deactivated_at')
                ->exists();

            if ($rendition === null
                || $rendition->state !== MediaRenditionState::Active
                || ! $hasReference
                || ! Storage::disk($rendition->disk)->exists($rendition->path)) {
                $unavailable[] = $usage;
            }
        }

        return [
            'state' => $unavailable === [] ? 'healthy' : 'degraded',
            'unavailable_usages' => $unavailable,
        ];
    }
}
