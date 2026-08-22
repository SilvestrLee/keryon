<?php

namespace App\PublicWebsite;

use App\Models\Church;

class PublicWebsiteResolver
{
    public function resolve(string $slug): ?Church
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
            return null;
        }

        return Church::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }
}
