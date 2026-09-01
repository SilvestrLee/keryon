<?php

namespace App\Platform\Operations;

use App\Enums\PlatformCapability;
use App\Models\PlatformAuditEvent;
use App\Models\PlatformMembership;
use App\Support\PlatformContext;
use DomainException;
use Illuminate\Support\Str;

abstract class PlatformOperation
{
    protected function actor(PlatformCapability $capability): PlatformMembership
    {
        $membership = app(PlatformContext::class)->currentMembership();
        abort_unless($membership?->hasCapability($capability), 403);

        return $membership;
    }

    protected function prior(string $event, string $targetType, string|int $targetId, string $correlationId): ?PlatformAuditEvent
    {
        return PlatformAuditEvent::query()->where('event_type', $event)->where('target_type', $targetType)
            ->where('target_id', (string) $targetId)->where('correlation_id', $correlationId)->first();
    }

    protected function requireReason(string $note): void
    {
        if (trim($note) === '' || mb_strlen($note) > 500) {
            throw new DomainException('A bounded operation reason is required.');
        }
    }

    protected function requireCorrelation(string $correlationId): void
    {
        if (! Str::isUuid($correlationId)) {
            throw new DomainException('A valid operation correlation identifier is required.');
        }
    }
}
