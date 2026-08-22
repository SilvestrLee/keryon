<?php

namespace App\Enums;

enum DesignPurpose: string
{
    case SERVICE = 'service';
    case ANNOUNCEMENT = 'announcement';
    case SCRIPTURE = 'scripture';
    case QUOTE = 'quote';
    case CAMPAIGN = 'campaign';
}
