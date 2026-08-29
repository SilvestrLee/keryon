<?php

namespace App\Enums;

enum MarketplaceDownloadOutcome: string
{
    case ISSUED = 'issued';
    case FAILED = 'failed';
}
