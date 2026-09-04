<?php

namespace App\Filament\Pages;

use App\Communications\OrganizationInbox\ChurchOrganizationCommunicationQuery;
use App\Enums\Capability;
use App\Support\TenantContext;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

/**
 * K-ORG-COMMS-001D §7-§9/§41-§45 — "Shared with your Church": every
 * durable Organization Communication delivery addressed to the current
 * Church, and nothing else. No Organization-wide browsing, no sibling-
 * Church visibility — `ChurchOrganizationCommunicationQuery` enforces
 * that scope, this page only renders what it returns.
 */
class OrganizationInbox extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.organization-inbox';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Communications';

    protected static ?string $navigationLabel = 'Organization Inbox';

    protected static ?string $title = 'Organization Inbox';

    protected static ?string $slug = 'organization-inbox';

    protected static ?int $navigationSort = 6;

    public string $search = '';

    public string $stateFilter = ChurchOrganizationCommunicationQuery::STATE_ALL;

    public string $kindFilter = '';

    public static function canAccess(): bool
    {
        $membership = app(TenantContext::class)->currentMembership();

        return $membership !== null && $membership->hasCapability(Capability::OrganizationCommunicationsView);
    }

    public function updatedSearch(): void
    {
        $this->resetPage('inboxPage');
    }

    public function updatedStateFilter(): void
    {
        $this->resetPage('inboxPage');
    }

    public function updatedKindFilter(): void
    {
        $this->resetPage('inboxPage');
    }

    public function deliveries(): LengthAwarePaginator
    {
        return app(ChurchOrganizationCommunicationQuery::class)->paginate($this->stateFilter, $this->kindFilter, $this->search);
    }

    public function detailUrl(string $uuid): string
    {
        return OrganizationInboxDetail::getUrl(['delivery' => $uuid]);
    }
}
