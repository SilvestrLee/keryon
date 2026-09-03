<?php

namespace App\Organizations\Communications;

use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationAuditSubjectType;
use App\Models\OrganizationAuditEvent;
use App\Models\OrganizationCommunication;
use App\Models\OrganizationCommunicationAsset;
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
        OrganizationCommunication|OrganizationCommunicationRevision|OrganizationCommunicationAsset $subject,
        OrganizationMembership $actor,
        array $new,
        ?array $previous = null,
    ): void {
        $communication = match (true) {
            $subject instanceof OrganizationCommunicationRevision => $subject->communication,
            $subject instanceof OrganizationCommunicationAsset => $subject->communication,
            default => $subject,
        };
        $subjectType = match (true) {
            $subject instanceof OrganizationCommunicationRevision => OrganizationAuditSubjectType::COMMUNICATION_REVISION,
            $subject instanceof OrganizationCommunicationAsset => OrganizationAuditSubjectType::COMMUNICATION_ASSET,
            default => OrganizationAuditSubjectType::COMMUNICATION,
        };

        OrganizationAuditEvent::query()->create([
            'organization_id' => $communication->organization_id,
            'event_type' => $event,
            'subject_type' => $subjectType,
            'subject_id' => $subject->id,
            'organization_unit_id' => $communication->governing_unit_id,
            'actor_user_id' => $actor->user_id,
            'previous_state' => $previous,
            'new_state' => ['actor_membership_id' => $actor->id, ...$new],
            'occurred_at' => now(),
        ]);
    }
}
