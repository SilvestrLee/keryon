<?php

namespace App\Search\Organization;

use App\Enums\OrganizationCapability;
use App\Enums\WorkspaceType;
use App\Filament\Organization\Pages\OrganizationChurches;
use App\Organizations\OrganizationScopeResolver;
use App\Search\SearchProvider;
use App\Search\SearchResult;

final class ChurchSearchProvider implements SearchProvider
{
    public function supports(WorkspaceType $workspace): bool
    {
        return $workspace === WorkspaceType::Organization;
    }

    public function eligible(): bool
    {
        return app(OrganizationScopeResolver::class)->hasOrganizationCapability(OrganizationCapability::ChurchesView);
    }

    public function search(string $term, int $limit): array
    {
        $pattern = '%'.addcslashes($term, '\\%_').'%';

        return app(OrganizationScopeResolver::class)->churchesInScope()->select('churches.id', 'churches.name', 'churches.slug')
            ->where(fn ($query) => $query->where('churches.name', 'like', $pattern)->orWhere('churches.slug', 'like', $pattern))
            ->orderBy('churches.name')->limit($limit)->get()
            ->map(fn ($church) => new SearchResult('Churches', 'Church governance record', $church->name, $church->slug, OrganizationChurches::getUrl(panel: 'organization')))
            ->all();
    }
}
