<?php

namespace App\Search\Central;

use App\Enums\PlatformCapability;
use App\Enums\WorkspaceType;
use App\Filament\Central\Pages\DomainDetail;
use App\Platform\Read\PlatformDomainQuery;
use App\Search\SearchProvider;
use App\Search\SearchResult;
use App\Support\PlatformContext;

final class DomainSearchProvider implements SearchProvider
{
    public function supports(WorkspaceType $w): bool
    {
        return $w === WorkspaceType::Central;
    }

    public function eligible(): bool
    {
        return app(PlatformContext::class)->hasCapability(PlatformCapability::DomainsView);
    }

    public function search(string $term, int $limit): array
    {
        return array_map(fn ($r) => new SearchResult('Domains', 'Domain', $r->hostname, $r->churchName.' · '.$r->status, DomainDetail::getUrl(['record' => $r->id])), app(PlatformDomainQuery::class)->search($term, $limit));
    }
}
