<?php

namespace App\Search\Church;

use App\Enums\Capability;
use App\Filament\Resources\PrayerRequestResource;
use App\Models\PrayerRequest;
use App\Search\Concerns\ChurchSearchProvider as ChurchSearchProviderConcern;
use App\Search\SearchProvider;
use App\Search\SearchResult;

final class CareSearchProvider implements SearchProvider
{
    use ChurchSearchProviderConcern;

    protected function capability(): Capability
    {
        return Capability::CareView;
    }

    public function search(string $term, int $limit): array
    {
        return PrayerRequest::query()->select('id', 'title', 'status')
            ->where('title', 'like', $this->pattern($term))->latest('submitted_at')->limit($limit)->get()
            ->map(fn (PrayerRequest $request) => new SearchResult('Care', 'Prayer request', $request->title, $request->status->label(), PrayerRequestResource::getUrl('view', ['record' => $request])))
            ->all();
    }
}
