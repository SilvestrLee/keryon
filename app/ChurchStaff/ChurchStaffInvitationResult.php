<?php

namespace App\ChurchStaff;

use App\Models\ChurchStaffInvitation;

final readonly class ChurchStaffInvitationResult
{
    public function __construct(
        public ChurchStaffInvitation $invitation,
        public ?string $rawToken,
        public bool $created,
    ) {}

    public function route(): ?string
    {
        return $this->rawToken === null ? null : route('church-staff-invitations.show', ['token' => $this->rawToken]);
    }
}
