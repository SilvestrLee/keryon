<?php

namespace App\Http\Controllers;

use App\Enums\MediaRenditionState;
use App\Models\MediaRendition;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicMediaRenditionController extends Controller
{
    public function __invoke(string $rendition): StreamedResponse
    {
        $record = MediaRendition::withoutGlobalScopes()
            ->where('uuid', $rendition)
            ->where('state', MediaRenditionState::Active->value)
            ->whereHas('publicReferences', fn ($query) => $query->whereNull('deactivated_at'))
            ->firstOrFail();

        abort_unless(Storage::disk($record->disk)->exists($record->path), 404);

        return Storage::disk($record->disk)->response($record->path, null, [
            'Content-Type' => $record->mime_type,
            'Cache-Control' => 'public, max-age=86400, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
