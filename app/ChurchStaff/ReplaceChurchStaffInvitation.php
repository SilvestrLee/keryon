<?php

namespace App\ChurchStaff;

use App\Models\ChurchMembership;
use App\Models\ChurchStaffInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReplaceChurchStaffInvitation
{
    public function __construct(private readonly RevokeChurchStaffInvitation $revoke, private readonly InviteChurchStaff $invite) {}

    public function execute(ChurchMembership $actor, ChurchStaffInvitation $invitation, array $roles): ChurchStaffInvitationResult
    {
        return DB::transaction(function () use ($actor, $invitation, $roles): ChurchStaffInvitationResult {
            $revoked = $this->revoke->execute($actor, $invitation);

            return $this->invite->execute($actor, $revoked->email_normalized, $roles, (string) Str::uuid(), $revoked);
        }, 3);
    }
}
