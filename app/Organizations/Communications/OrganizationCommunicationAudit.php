<?php

namespace App\Organizations\Communications;

use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationAuditSubjectType;
use App\Models\OrganizationAuditEvent;
use App\Models\OrganizationCommunication;
use App\Models\OrganizationCommunicationRevision;
use App\Models\OrganizationMembership;

class OrganizationCommunicationAudit
{
    /**
     * @param  array<string, scalar|null>  $new
     * @param  array<string, scalar|null>|null  $previous
     */
    public function record(
        OrganizationAuditEventType $event,
        OrganizationCommunication|OrganizationCommunicationRevision $subject,
        OrganizationMembership $actor,
        array $new,
        ?array $previous = null,
    ): void {
        $communication = $subject instanceof OrganizationCommunicationRevision
            ? $subject->communication
            : $subject;

        OrganizationAuditEvent::query()->create([
            'organization_id' => $communication->organization_id,
            'event_type' => $event,
            'subject_type' => $subject instanceof OrganizationCommunicationRevision
                ? OrganizationAuditSubjectType::COMMUNICATION_REVISION
                : OrganizationAuditSubjectType::COMMUNICATION,
            'subject_id' => $subject->id,
            'organization_unit_id' => $communication->governing_unit_id,
            'actor_user_id' => $actor->user_id,
            'previous_state' => $previous,
            'new_state' => ['actor_membership_id' => $actor->id, ...$new],
            'occurred_at' => now(),
        ]);
    }
}
