<?php

namespace App\ChurchStaff;

use App\Enums\ChurchAccessAuditEventType;
use App\Enums\ChurchStaffInvitationStatus;
use App\Enums\InvitationDeliverySubjectType;
use App\InvitationDelivery\SupersedeInvitationDeliveries;
use App\Models\ChurchMembership;
use App\Models\ChurchStaffInvitation;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RevokeChurchStaffInvitation
{
    public function execute(ChurchMembership $actor, ChurchStaffInvitation $invitation): ChurchStaffInvitation
    {
        ChurchStaffAuthorizer::manage($actor, $invitation->church_id);

        return DB::transaction(function () use ($actor, $invitation): ChurchStaffInvitation {
            $locked = ChurchStaffInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if ($locked->status !== ChurchStaffInvitationStatus::PENDING) {
                throw new DomainException('Only a pending invitation may be revoked.');
            }
            $locked->forceFill(['status' => ChurchStaffInvitationStatus::REVOKED, 'pending_identity' => null, 'token_hash' => null, 'token_expires_at' => null, 'revoked_at' => now()])->save();
            SupersedeInvitationDeliveries::for(InvitationDeliverySubjectType::CHURCH_STAFF_INVITATION, $locked->id);
            ChurchAccessAudit::record(ChurchAccessAuditEventType::STAFF_INVITATION_REVOKED, $locked->church_id, 'invitation', $locked->id, $actor, ['status' => 'pending'], ['status' => 'revoked']);

            return $locked->fresh();
        }, 3);
    }
}
