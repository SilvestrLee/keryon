<?php

namespace App\Enums;

enum ChurchActivationStatus: string
{
    case PENDING = 'pending';
    case COMMERCIAL_REVIEW = 'commercial_review';
    case ACCEPTED = 'accepted';
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';
}
