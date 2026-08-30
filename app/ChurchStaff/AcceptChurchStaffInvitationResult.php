<?php

namespace App\ChurchStaff;

use App\Models\ChurchMembership;
use App\Models\ChurchStaffInvitation;

final readonly class AcceptChurchStaffInvitationResult
{
    public function __construct(public ChurchStaffInvitation $invitation, public ChurchMembership $membership, public bool $accepted) {}
}
