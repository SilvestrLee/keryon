<?php

namespace App\Search\Central;

use App\Enums\PlatformCapability;
use App\Enums\WorkspaceType;
use App\Filament\Central\Pages\ChurchDetail;
use App\Platform\Read\PlatformChurchQuery;
use App\Search\SearchProvider;
use App\Search\SearchResult;
use App\Support\PlatformContext;

final class ChurchSearchProvider implements SearchProvider
{
    public function supports(WorkspaceType $w): bool
    {
        return $w === WorkspaceType::Central;
    }

    public function eligible(): bool
    {
        return app(PlatformContext::class)->hasCapability(PlatformCapability::ChurchesView);
    }

    public function search(string $term, int $limit): array
    {
        return array_map(fn ($r) => new SearchResult('Churches', 'Church', $r->name, $r->slug, ChurchDetail::getUrl(['record' => $r->id])), app(PlatformChurchQuery::class)->search($term, $limit));
    }
}
