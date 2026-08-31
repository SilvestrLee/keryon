<?php

namespace App\Search\Church;

use App\Enums\Capability;
use App\Filament\Resources\ContentItemResource;
use App\Models\ContentItem;
use App\Search\Concerns\ChurchSearchProvider as ChurchSearchProviderConcern;
use App\Search\SearchProvider;
use App\Search\SearchResult;

final class ContentSearchProvider implements SearchProvider
{
    use ChurchSearchProviderConcern;

    protected function capability(): Capability
    {
        return Capability::ContentView;
    }

    public function search(string $term, int $limit): array
    {
        return ContentItem::query()->select('id', 'title', 'status')
            ->where('title', 'like', $this->pattern($term))->orderBy('title')->limit($limit)->get()
            ->map(fn (ContentItem $item) => new SearchResult('Content', 'Content', $item->title, $item->status->label(), ContentItemResource::getUrl('view', ['record' => $item])))
            ->all();
    }
}
