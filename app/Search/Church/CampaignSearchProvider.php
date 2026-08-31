<?php

namespace App\Search\Church;

use App\Enums\Capability;
use App\Filament\Pages\CampaignWorkspace;
use App\Models\Campaign;
use App\Search\Concerns\ChurchSearchProvider as ChurchSearchProviderConcern;
use App\Search\SearchProvider;
use App\Search\SearchResult;

final class CampaignSearchProvider implements SearchProvider
{
    use ChurchSearchProviderConcern;

    protected function capability(): Capability
    {
        return Capability::CampaignsView;
    }

    public function search(string $term, int $limit): array
    {
        return Campaign::query()->select('id', 'title', 'status')
            ->where('title', 'like', $this->pattern($term))->orderBy('title')->limit($limit)->get()
            ->map(fn (Campaign $campaign) => new SearchResult('Campaigns', 'Campaign', $campaign->title, $campaign->status->label(), CampaignWorkspace::getUrl(['campaign' => $campaign])))
            ->all();
    }
}
