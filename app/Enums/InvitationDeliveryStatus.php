<?php

namespace App\Enums;

enum InvitationDeliveryStatus: string
{
    case REQUESTED = 'requested';
    case QUEUED = 'queued';
    case PROVIDER_ACCEPTED = 'provider_accepted';
    case FAILED = 'failed';
    case BOUNCED = 'bounced';
    case COMPLAINED = 'complained';
    case SUPPRESSED = 'suppressed';
    case SUPERSEDED = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::PROVIDER_ACCEPTED => 'Sent',
            self::FAILED => 'Delivery failed',
            self::BOUNCED => 'Bounced',
            self::COMPLAINED => 'Complaint reported',
            self::SUPPRESSED => 'Suppressed',
            self::QUEUED, self::REQUESTED => 'Queued',
            self::SUPERSEDED => 'Superseded',
        };
    }
}
