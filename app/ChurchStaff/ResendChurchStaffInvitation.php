<?php

namespace App\ChurchStaff;

use App\Enums\ChurchAccessAuditEventType;
use App\Enums\ChurchStaffInvitationStatus;
use App\InvitationDelivery\InvitationDeliveryRateLimiter;
use App\Models\ChurchMembership;
use App\Models\ChurchStaffInvitation;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ResendChurchStaffInvitation
{
    public function execute(ChurchMembership $actor, ChurchStaffInvitation $invitation): ChurchStaffInvitationResult
    {
        ChurchStaffAuthorizer::manage($actor, $invitation->church_id);
        app(InvitationDeliveryRateLimiter::class)->ensureSendAllowed('church_staff_invitation:'.$invitation->id, $invitation->church_id, $invitation->email_normalized);

        return DB::transaction(function () use ($actor, $invitation): ChurchStaffInvitationResult {
            $locked = ChurchStaffInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if ($locked->status !== ChurchStaffInvitationStatus::PENDING) {
                throw new DomainException('Only a pending invitation may be resent.');
            }
            $token = ChurchStaffInvitationTokenService::generate();
            $locked->forceFill(['token_hash' => ChurchStaffInvitationTokenService::hash($token), 'token_expires_at' => now()->addHours(72), 'sent_at' => now()])->save();
            ChurchAccessAudit::record(ChurchAccessAuditEventType::STAFF_INVITATION_RESENT, $locked->church_id, 'invitation', $locked->id, $actor, null, ['roles' => $locked->roles()->pluck('role')->map->value->all()]);

            return new ChurchStaffInvitationResult($locked->fresh('roles'), $token, false);
        }, 3);
    }
}
