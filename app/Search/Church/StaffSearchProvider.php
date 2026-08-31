<?php

namespace App\Search\Church;

use App\Enums\Capability;
use App\Filament\Pages\ChurchStaffAccess;
use App\Models\ChurchMembership;
use App\Search\Concerns\ChurchSearchProvider as ChurchSearchProviderConcern;
use App\Search\SearchProvider;
use App\Search\SearchResult;
use App\Support\TenantContext;

final class StaffSearchProvider implements SearchProvider
{
    use ChurchSearchProviderConcern;

    protected function capability(): Capability
    {
        return Capability::StaffView;
    }

    public function search(string $term, int $limit): array
    {
        $pattern = $this->pattern($term);

        return ChurchMembership::query()->active()
            ->where('church_id', app(TenantContext::class)->currentChurchId())
            ->with('user:id,name,email')
            ->whereHas('user', fn ($query) => $query->where('name', 'like', $pattern)->orWhere('email', 'like', $pattern))
            ->limit($limit)->get()
            ->map(fn (ChurchMembership $membership) => new SearchResult('Staff', 'Church staff', $membership->user->name, $membership->user->email, ChurchStaffAccess::getUrl()))
            ->all();
    }
}
