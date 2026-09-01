<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Read\PlatformDeliveryQuery;
use Filament\Pages\Page;
use Livewire\WithPagination;

class Deliveries extends Page
{
    use InteractsWithPlatformWorkspace,WithPagination;

    protected string $view = 'filament.central.pages.collection';

    protected static ?string $title = 'Invitation Delivery';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    public string $status = '';

    public string $type = '';

    public string $failure = '';

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::DeliveriesView;
    }

    public function records()
    {
        return app(PlatformDeliveryQuery::class)->paginate($this->status, $this->type, $this->failure);
    }

    public function columns(): array
    {
        return ['Type', 'Church', 'Recipient', 'Status', 'Attempts', 'Failure', 'Requested'];
    }

    public function cells($r): array
    {
        return [$r->type, $r->churchName ?? 'Unknown', $r->recipient, $r->status, (string) $r->attemptCount, $r->failureCategory ?? 'None', $r->requestedAt];
    }

    public function detailUrl($r): string
    {
        return DeliveryDetail::getUrl(['record' => $r->id]);
    }
}
