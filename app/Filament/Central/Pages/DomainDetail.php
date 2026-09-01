<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Read\PlatformDomainQuery;
use Filament\Pages\Page;

class DomainDetail extends Page
{
    use InteractsWithPlatformWorkspace;

    protected string $view = 'filament.central.pages.detail';

    protected static ?string $slug = 'domains/{record}';

    protected static bool $shouldRegisterNavigation = false;

    public int $record;

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::DomainsView;
    }

    public function item()
    {
        return app(PlatformDomainQuery::class)->find($this->record);
    }

    public function sections($r): array
    {
        $s = ['Domain' => ['Hostname' => $r->hostname, 'Display hostname' => $r->displayHostname ?? $r->hostname, 'Church' => $r->churchName, 'Role' => $r->primary ? 'Primary' : 'Companion', 'Lifecycle' => $r->status], 'Verification' => ['Ownership' => $r->ownershipVerified ? 'Verified' : 'Pending', 'Routing' => $r->routingVerified ? 'Verified' : 'Pending', 'TLS' => $r->tlsStatus, 'Failure code' => $r->failureCode ?? 'None', 'Consecutive failures' => (string) $r->failureCount, 'Last checked' => $r->lastCheckedAt ?? 'Never', 'Released' => $r->releasedAt ?? 'No']];
        if ($r->events) {
            $s['Recent domain evidence'] = collect($r->events)->mapWithKeys(fn ($v) => [$v['occurred_at'] => $v['event'].($v['failure'] ? ' · '.$v['failure'] : '')])->all();
        }

        return $s;
    }
}
