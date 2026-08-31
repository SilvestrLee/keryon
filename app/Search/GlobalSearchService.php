<?php

namespace App\Search;

use App\Enums\WorkspaceType;
use App\Search\Church\CampaignSearchProvider;
use App\Search\Church\CareSearchProvider;
use App\Search\Church\CongregationSearchProvider;
use App\Search\Church\ContentSearchProvider;
use App\Search\Church\StaffSearchProvider;
use App\Search\Church\WebsiteSearchProvider;
use App\Search\Organization\ChurchSearchProvider;
use App\Search\Organization\UnitSearchProvider;
use Illuminate\Support\Collection;

final readonly class GlobalSearchService
{
    /** @return Collection<string, Collection<int, SearchResult>> */
    public function search(WorkspaceType $workspace, string $query): Collection
    {
        $query = trim($query);
        if (mb_strlen($query) < (int) config('keryon.search.minimum_length', 2)) {
            return collect();
        }

        $providerLimit = (int) config('keryon.search.provider_limit', 4);
        $combinedLimit = (int) config('keryon.search.combined_limit', 12);
        $results = collect($this->providers())
            ->filter(fn (SearchProvider $provider) => $provider->supports($workspace) && $provider->eligible())
            ->flatMap(fn (SearchProvider $provider) => $provider->search($query, $providerLimit))
            ->take($combinedLimit);

        return $results->groupBy('group');
    }

    /** @return list<SearchProvider> */
    private function providers(): array
    {
        return [
            app(ContentSearchProvider::class),
            app(CampaignSearchProvider::class),
            app(CongregationSearchProvider::class),
            app(StaffSearchProvider::class),
            app(WebsiteSearchProvider::class),
            app(CareSearchProvider::class),
            app(UnitSearchProvider::class),
            app(ChurchSearchProvider::class),
        ];
    }
}
