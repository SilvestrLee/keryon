<?php

namespace App\Search\Organization;

use App\Enums\OrganizationCapability;
use App\Enums\WorkspaceType;
use App\Filament\Organization\Pages\OrganizationUnits;
use App\Organizations\OrganizationScopeResolver;
use App\Search\SearchProvider;
use App\Search\SearchResult;

final class UnitSearchProvider implements SearchProvider
{
    public function supports(WorkspaceType $workspace): bool
    {
        return $workspace === WorkspaceType::Organization;
    }

    public function eligible(): bool
    {
        return app(OrganizationScopeResolver::class)->hasOrganizationCapability(OrganizationCapability::UnitsView);
    }

    public function search(string $term, int $limit): array
    {
        $pattern = '%'.addcslashes($term, '\\%_').'%';

        return app(OrganizationScopeResolver::class)->unitsInScope()->select('organization_units.id', 'organization_units.name', 'organization_units.code')
            ->where(fn ($query) => $query->where('organization_units.name', 'like', $pattern)->orWhere('organization_units.code', 'like', $pattern))
            ->orderBy('organization_units.name')->limit($limit)->get()
            ->map(fn ($unit) => new SearchResult('Organization units', 'Organization unit', $unit->name, $unit->code, OrganizationUnits::getUrl(panel: 'organization')))
            ->all();
    }
}
