<?php

namespace App\ChurchStaff;

use App\Enums\ChurchAccessAuditEventType;
use App\Models\ChurchAccessAuditEvent;
use App\Models\ChurchMembership;

final class ChurchAccessAudit
{
    public static function record(ChurchAccessAuditEventType $type, int $churchId, string $subjectType, int $subjectId, ?ChurchMembership $actor, ?array $previous, ?array $new): void
    {
        ChurchAccessAuditEvent::query()->create([
            'church_id' => $churchId, 'event_type' => $type, 'subject_type' => $subjectType,
            'subject_id' => $subjectId, 'actor_user_id' => $actor?->user_id,
            'actor_membership_id' => $actor?->id, 'previous_state' => $previous,
            'new_state' => $new, 'occurred_at' => now(),
        ]);
    }
}
