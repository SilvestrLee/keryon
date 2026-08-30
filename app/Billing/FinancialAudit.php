<?php

namespace App\Billing;

use App\Enums\CommercialAuditEventType;
use App\Models\CommercialAuditEvent;

final class FinancialAudit
{
    public static function record(CommercialAuditEventType $type, array $targets, ?int $actor, ?array $previous, ?array $new): void
    {
        CommercialAuditEvent::query()->create(array_merge([
            'event_type' => $type, 'actor_user_id' => $actor, 'previous_state' => $previous,
            'new_state' => $new, 'occurred_at' => now(),
        ], $targets));
    }
}
