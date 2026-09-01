<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Read\PlatformCommercialQuery;
use Filament\Pages\Page;
use Livewire\WithPagination;

class Subscriptions extends Page
{
    use InteractsWithPlatformWorkspace,WithPagination;

    protected string $view = 'filament.central.pages.collection';

    protected static ?string $title = 'Subscriptions';

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    public string $search = '';

    public string $status = '';

    public string $market = '';

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::SubscriptionsView;
    }

    public function records()
    {
        return app(PlatformCommercialQuery::class)->paginate($this->search, $this->status, $this->market);
    }

    public function columns(): array
    {
        return ['Church', 'Status', 'Plan', 'Market', 'Trial ends', 'Current period'];
    }

    public function cells($r): array
    {
        return [$r->churchName, $r->status, $r->planVersion ?? 'None', $r->market ?? 'None', $r->trialEndsAt ?? 'None', ($r->periodStart ?? 'None').' to '.($r->periodEnd ?? 'None')];
    }

    public function detailUrl($r): string
    {
        return SubscriptionDetail::getUrl(['record' => $r->id]);
    }
}
