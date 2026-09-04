<?php

namespace App\Enums;

/**
 * K-ORG-COMMS-001C §18/§51 — only AVAILABLE is ever written in 001C.
 * WITHDRAWN exists so the column can carry a future explicit sender
 * withdrawal action (deliberately not implemented here — see the
 * report's Product Office decision F) without a later migration.
 */
enum OrganizationCommunicationDeliveryState: string
{
    case AVAILABLE = 'available';
    case WITHDRAWN = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Available',
            self::WITHDRAWN => 'Withdrawn',
        };
    }
}
