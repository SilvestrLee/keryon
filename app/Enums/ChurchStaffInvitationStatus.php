<?php

namespace App\Enums;

enum ChurchStaffInvitationStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending invitation',
            self::ACCEPTED => 'Accepted',
            self::REVOKED => 'Revoked',
            self::EXPIRED => 'Expired',
        };
    }
}
