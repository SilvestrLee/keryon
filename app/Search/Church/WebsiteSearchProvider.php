<?php

namespace App\Search\Church;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Enums\Capability;
use App\Enums\EntitlementKey;
use App\Enums\WorkspaceType;
use App\Filament\Clusters\Website\Pages\WebsiteOverview;
use App\Search\SearchProvider;
use App\Search\SearchResult;
use App\Support\TenantContext;

final class WebsiteSearchProvider implements SearchProvider
{
    public function supports(WorkspaceType $workspace): bool
    {
        return $workspace === WorkspaceType::Church;
    }

    public function eligible(): bool
    {
        $tenant = app(TenantContext::class);

        return ($tenant->currentMembership()?->hasCapability(Capability::WebsiteContentView) ?? false)
            && $tenant->currentChurch() !== null
            && app(EntitlementResolver::class)->allows($tenant->currentChurch(), EntitlementKey::WebsiteEnabled);
    }

    public function search(string $term, int $limit): array
    {
        if (! str_contains(mb_strtolower('website home about leadership ministries contact'), mb_strtolower($term))) {
            return [];
        }

        return [new SearchResult('Website', 'Website', 'Website workspace', 'Manage public Website content', WebsiteOverview::getUrl())];
    }
}
