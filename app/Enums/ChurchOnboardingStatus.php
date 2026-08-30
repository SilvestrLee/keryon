<?php

namespace App\Enums;

enum ChurchOnboardingStatus: string
{
    case NOT_STARTED = 'not_started';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case DISMISSED = 'dismissed';
}
