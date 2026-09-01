<?php

namespace App\Search\Central;

use App\Enums\PlatformCapability;
use App\Enums\WorkspaceType;
use App\Filament\Central\Pages\OrganizationDetail;
use App\Platform\Read\PlatformOrganizationQuery;
use App\Search\SearchProvider;
use App\Search\SearchResult;
use App\Support\PlatformContext;

final class OrganizationSearchProvider implements SearchProvider
{
    public function supports(WorkspaceType $w): bool
    {
        return $w === WorkspaceType::Central;
    }

    public function eligible(): bool
    {
        return app(PlatformContext::class)->hasCapability(PlatformCapability::OrganizationsView);
    }

    public function search(string $term, int $limit): array
    {
        return array_map(fn ($r) => new SearchResult('Organizations', 'Organization', $r->name, $r->slug, OrganizationDetail::getUrl(['record' => $r->id])), app(PlatformOrganizationQuery::class)->search($term, $limit));
    }
}
