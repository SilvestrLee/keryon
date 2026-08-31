<?php

namespace App\Enums;

enum InvitationDeliveryFailureCategory: string
{
    case CONFIGURATION = 'configuration';
    case TRANSIENT_TRANSPORT = 'transient_transport';
    case PERMANENT_TRANSPORT = 'permanent_transport';
    case SUPPRESSED = 'suppressed';
    case INVALID_RECIPIENT = 'invalid_recipient';
    case EXPIRED = 'expired';
    case SUPERSEDED = 'superseded';
}
