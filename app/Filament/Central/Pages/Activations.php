<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Read\PlatformActivationQuery;
use Filament\Pages\Page;
use Livewire\WithPagination;

class Activations extends Page
{
    use InteractsWithPlatformWorkspace,WithPagination;

    protected string $view = 'filament.central.pages.collection';

    protected static ?string $title = 'Activations';

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    public string $search = '';

    public string $status = '';

    public string $country = '';

    public string $delivery = '';

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::ActivationsView;
    }

    public function records()
    {
        return app(PlatformActivationQuery::class)->paginate($this->search, $this->status, $this->country, $this->delivery);
    }

    public function columns(): array
    {
        return ['Church', 'Primary', 'Status', 'Market / plan', 'Delivery', 'Expires'];
    }

    public function cells($r): array
    {
        return [$r->churchName, $r->primaryEmail, $r->status, ($r->market ?? 'None').' · '.($r->planVersion ?? 'None'), $r->deliveryStatus ?? 'Not requested', $r->expiresAt ?? 'None'];
    }

    public function detailUrl($r): string
    {
        return ActivationDetail::getUrl(['record' => $r->id]);
    }
}
