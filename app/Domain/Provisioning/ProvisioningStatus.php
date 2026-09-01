<?php

namespace App\Domain\Provisioning;

enum ProvisioningStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
    case Unavailable = 'unavailable';
}
