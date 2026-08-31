<?php

namespace App\Website;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Enums\EntitlementKey;
use App\Models\Church;
use App\Models\WebsiteSettings;
use App\PublicWebsite\PublicWebsiteUrl;

final readonly class ChurchPublicUrlResolver
{
    public function __construct(
        private EntitlementResolver $entitlements,
        private PublicWebsiteUrl $urls,
    ) {}

    public function resolve(Church $church): ?string
    {
        if (! $church->is_active || ! $this->entitlements->allows($church, EntitlementKey::WebsiteEnabled)) {
            return null;
        }

        $published = WebsiteSettings::query()->whereNotNull('current_publication_id')->exists();

        return $published ? $this->urls->page($church) : null;
    }
}
