<?php

namespace App\Search\Church;

use App\Enums\Capability;
use App\Filament\Resources\CongregationResource;
use App\Models\CongregationMember;
use App\Search\Concerns\ChurchSearchProvider as ChurchSearchProviderConcern;
use App\Search\SearchProvider;
use App\Search\SearchResult;

final class CongregationSearchProvider implements SearchProvider
{
    use ChurchSearchProviderConcern;

    protected function capability(): Capability
    {
        return Capability::CongregationView;
    }

    public function search(string $term, int $limit): array
    {
        $pattern = $this->pattern($term);

        return CongregationMember::query()->select('id', 'first_name', 'last_name', 'status')
            ->where(fn ($query) => $query->where('first_name', 'like', $pattern)->orWhere('last_name', 'like', $pattern))
            ->orderBy('first_name')->limit($limit)->get()
            ->map(fn (CongregationMember $member) => new SearchResult('People', 'Congregation member', $member->full_name, $member->status->label(), CongregationResource::getUrl('view', ['record' => $member])))
            ->all();
    }
}
