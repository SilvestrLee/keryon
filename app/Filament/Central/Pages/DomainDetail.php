<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithGovernedOperation;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Operations\PlatformRetryDomain;
use App\Platform\Read\PlatformDomainQuery;
use App\Support\PlatformContext;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class DomainDetail extends Page
{
    use InteractsWithGovernedOperation, InteractsWithPlatformWorkspace;

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

    public function canRetry(): bool
    {
        return ! in_array($this->item()->status, ['disabled', 'released'], true) && app(PlatformContext::class)->hasCapability(PlatformCapability::PlatformDomainRetry);
    }

    public function operationView(): string
    {
        return 'filament.central.pages.partials.domain-actions';
    }

    public function runOperation(PlatformRetryDomain $retry): void
    {
        abort_unless($this->operation === 'retry' && $this->canRetry(), 403);
        $reason = $this->authorizeOperation();
        try {
            $result = $retry->execute($this->record, $reason, $this->reasonNote, $this->correlationId);
            Notification::make()->success()->title($result->message)->send();
            $this->cancelOperation();
        } catch (DomainException $exception) {
            $this->addError('operation', $exception->getMessage());
        }
    }
}
