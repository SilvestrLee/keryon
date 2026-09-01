<?php

namespace App\Search\Central;

use App\Enums\PlatformCapability;
use App\Enums\WorkspaceType;
use App\Filament\Central\Pages\SubscriptionDetail;
use App\Platform\Read\PlatformCommercialQuery;
use App\Search\SearchProvider;
use App\Search\SearchResult;
use App\Support\PlatformContext;

final class SubscriptionSearchProvider implements SearchProvider
{
    public function supports(WorkspaceType $w): bool
    {
        return $w === WorkspaceType::Central;
    }

    public function eligible(): bool
    {
        return app(PlatformContext::class)->hasCapability(PlatformCapability::SubscriptionsView);
    }

    public function search(string $term, int $limit): array
    {
        return array_map(fn ($r) => new SearchResult('Subscriptions', 'Subscription', $r->churchName, $r->status.' · '.$r->uuid, SubscriptionDetail::getUrl(['record' => $r->id])), app(PlatformCommercialQuery::class)->search($term, $limit));
    }
}
