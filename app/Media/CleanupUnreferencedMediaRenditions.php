<?php

namespace App\Media;

use App\Enums\MediaRenditionState;
use App\Models\MediaPublicReference;
use App\Models\MediaRendition;
use Illuminate\Support\Facades\Storage;

class CleanupUnreferencedMediaRenditions
{
    public function handle(): int
    {
        $cutoff = now()->subHours((int) config('media.rendition_cleanup_grace_hours', 24));
        $cleaned = 0;

        MediaRendition::withoutGlobalScopes()
            ->where('state', MediaRenditionState::Active->value)
            ->each(function (MediaRendition $rendition) use (&$cleaned, $cutoff): void {
                $references = MediaPublicReference::withoutGlobalScopes()
                    ->where('media_rendition_id', $rendition->id)
                    ->get();

                if ($references->contains(fn (MediaPublicReference $reference): bool => $reference->deactivated_at === null)) {
                    return;
                }

                $eligibleAt = $references->max('deactivated_at') ?? $rendition->activated_at;

                if ($eligibleAt === null || $eligibleAt->isAfter($cutoff)) {
                    return;
                }

                Storage::disk($rendition->disk)->delete($rendition->path);
                $rendition->forceFill(['state' => MediaRenditionState::Revoked, 'revoked_at' => now()])->save();
                $cleaned++;
            });

        return $cleaned;
    }
}
