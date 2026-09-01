<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Read\PlatformChurchQuery;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

class Churches extends Page
{
    use InteractsWithPlatformWorkspace,WithPagination;

    protected string $view = 'filament.central.pages.collection';

    protected static ?string $title = 'Churches';

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    public string $search = '';

    public string $state = '';

    public string $country = '';

    public string $activation = '';

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::ChurchesView;
    }

    public function records(): LengthAwarePaginator
    {
        return app(PlatformChurchQuery::class)->paginate($this->search, $this->state, $this->country, $this->activation);
    }

    public function columns(): array
    {
        return ['Church', 'Status', 'Primary administrator', 'Organization', 'Subscription', 'Website / domain', 'Activation'];
    }

    public function cells($r): array
    {
        return [$r->name, $r->active ? 'Active' : 'Inactive', $r->primaryEmail ?? 'Not assigned', $r->organizationName ?? 'Independent', $r->subscriptionStatus ?? 'None', ($r->websitePublished ? 'Published' : 'Private').' · '.$r->domainState, $r->activationStatus ?? 'None'];
    }

    public function detailUrl($r): string
    {
        return ChurchDetail::getUrl(['record' => $r->id]);
    }
}
