<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Read\PlatformDomainQuery;
use Filament\Pages\Page;
use Livewire\WithPagination;

class Domains extends Page
{
    use InteractsWithPlatformWorkspace,WithPagination;

    protected string $view = 'filament.central.pages.collection';

    protected static ?string $title = 'Domains';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    public string $search = '';

    public string $status = '';

    public string $tls = '';

    public string $primary = '';

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::DomainsView;
    }

    public function records()
    {
        return app(PlatformDomainQuery::class)->paginate($this->search, $this->status, $this->tls, $this->primary);
    }

    public function columns(): array
    {
        return ['Hostname', 'Church', 'Role', 'Lifecycle', 'Ownership / routing', 'TLS', 'Failure'];
    }

    public function cells($r): array
    {
        return [$r->displayHostname ?? $r->hostname, $r->churchName, $r->primary ? 'Primary' : 'Companion', $r->status, ($r->ownershipVerified ? 'Verified' : 'Pending').' · '.($r->routingVerified ? 'Routed' : 'Unrouted'), $r->tlsStatus, $r->failureCode ?? 'None'];
    }

    public function detailUrl($r): string
    {
        return DomainDetail::getUrl(['record' => $r->id]);
    }
}
