<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Read\PlatformOrganizationQuery;
use Filament\Pages\Page;
use Livewire\WithPagination;

class Organizations extends Page
{
    use InteractsWithPlatformWorkspace,WithPagination;

    protected string $view = 'filament.central.pages.collection';

    protected static ?string $title = 'Organizations';

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    public string $search = '';

    public string $status = '';

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::OrganizationsView;
    }

    public function records()
    {
        return app(PlatformOrganizationQuery::class)->paginate($this->search, $this->status);
    }

    public function columns(): array
    {
        return ['Organization', 'Status', 'Root unit', 'Units', 'Churches', 'Active members'];
    }

    public function cells($r): array
    {
        return [$r->name, $r->status, $r->rootUnit ?? 'Not set', (string) $r->unitCount, (string) $r->churchCount, (string) $r->membershipCount];
    }

    public function detailUrl($r): string
    {
        return OrganizationDetail::getUrl(['record' => $r->id]);
    }
}
