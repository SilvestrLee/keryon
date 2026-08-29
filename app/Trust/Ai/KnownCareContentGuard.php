<?php

namespace App\Trust\Ai;

use App\Models\PrayerRequest;

class KnownCareContentGuard
{
    public function assertEligible(string $content, int $churchId): void
    {
        $candidate = $this->normalize($content);

        $matchesKnownCare = PrayerRequest::withoutGlobalScopes()
            ->where('church_id', $churchId)
            ->pluck('request')
            ->contains(fn (string $request): bool => hash_equals($this->normalize($request), $candidate));

        if ($matchesKnownCare) {
            throw new AiProcessingDeniedException('known_care_content');
        }
    }

    private function normalize(string $content): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $content)));
    }
}
