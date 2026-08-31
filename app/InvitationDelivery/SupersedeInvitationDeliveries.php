<?php

namespace App\InvitationDelivery;

use App\Enums\InvitationDeliveryStatus;
use App\Enums\InvitationDeliverySubjectType;
use App\Models\InvitationDeliveryAttempt;

final class SupersedeInvitationDeliveries
{
    public static function for(InvitationDeliverySubjectType $type, int $subjectId): void
    {
        InvitationDeliveryAttempt::query()
            ->where($type === InvitationDeliverySubjectType::CHURCH_ACTIVATION ? 'church_activation_id' : 'church_staff_invitation_id', $subjectId)
            ->whereIn('status', [InvitationDeliveryStatus::REQUESTED->value, InvitationDeliveryStatus::QUEUED->value, InvitationDeliveryStatus::FAILED->value])
            ->update(['status' => InvitationDeliveryStatus::SUPERSEDED->value, 'superseded_at' => now(), 'sensitive_payload' => null, 'sensitive_payload_cleared_at' => now()]);
    }
}
