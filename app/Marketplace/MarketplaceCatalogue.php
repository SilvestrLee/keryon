<?php

namespace App\Marketplace;

use App\Models\MarketplaceItem;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use LogicException;

class MarketplaceCatalogue
{
    /** @return Collection<int, MarketplaceItem> */
    public function published(): Collection
    {
        Gate::authorize('viewAny', MarketplaceItem::class);
        $this->requireChurchContext();

        return MarketplaceItem::query()
            ->published()
            ->withDownloadableSource()
            ->with(['category', 'previews' => fn ($query) => $query->orderBy('sort_order')])
            ->orderByDesc('published_at')
            ->get();
    }

    public function findPublishedBySlug(string $slug): MarketplaceItem
    {
        Gate::authorize('viewAny', MarketplaceItem::class);
        $this->requireChurchContext();

        return MarketplaceItem::query()
            ->published()
            ->withDownloadableSource()
            ->with(['category', 'previews' => fn ($query) => $query->orderBy('sort_order')])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function requireChurchContext(): void
    {
        if (app(TenantContext::class)->currentChurchId() === null) {
            throw new LogicException('The private Marketplace requires an active Church context.');
        }
    }
}
