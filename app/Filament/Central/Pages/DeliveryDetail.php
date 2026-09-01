<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Read\PlatformDeliveryQuery;
use Filament\Pages\Page;

class DeliveryDetail extends Page
{
    use InteractsWithPlatformWorkspace;

    protected string $view = 'filament.central.pages.detail';

    protected static ?string $slug = 'deliveries/{record}';

    protected static bool $shouldRegisterNavigation = false;

    public int $record;

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::DeliveriesView;
    }

    public function item()
    {
        return app(PlatformDeliveryQuery::class)->find($this->record);
    }

    public function sections($r): array
    {
        return ['Delivery' => ['UUID' => $r->uuid, 'Type' => $r->type, 'Church' => $r->churchName ?? 'Unknown', 'Recipient' => $r->recipient, 'Status' => $r->status, 'Attempts' => (string) $r->attemptCount, 'Failure' => $r->failureCategory ?? 'None'], 'Provider evidence' => ['Account' => $r->providerAccount ?? 'Not assigned', 'Reference' => $r->providerReference ?? 'None'], 'Timeline' => ['Requested' => $r->requestedAt, 'Queued' => $r->queuedAt ?? 'Not queued', 'Accepted' => $r->acceptedAt ?? 'Not accepted', 'Failed' => $r->failedAt ?? 'No', 'Bounced' => $r->bouncedAt ?? 'No', 'Complained' => $r->complainedAt ?? 'No']];
    }
}
