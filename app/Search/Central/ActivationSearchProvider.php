<?php

namespace App\Search\Central;

use App\Enums\PlatformCapability;
use App\Enums\WorkspaceType;
use App\Filament\Central\Pages\ActivationDetail;
use App\Platform\Read\PlatformActivationQuery;
use App\Search\SearchProvider;
use App\Search\SearchResult;
use App\Support\PlatformContext;

final class ActivationSearchProvider implements SearchProvider
{
    public function supports(WorkspaceType $w): bool
    {
        return $w === WorkspaceType::Central;
    }

    public function eligible(): bool
    {
        return app(PlatformContext::class)->hasCapability(PlatformCapability::ActivationsView);
    }

    public function search(string $term, int $limit): array
    {
        return array_map(fn ($r) => new SearchResult('Activations', 'Activation', $r->churchName, $r->status.' · '.$r->uuid, ActivationDetail::getUrl(['record' => $r->id])), app(PlatformActivationQuery::class)->search($term, $limit));
    }
}
