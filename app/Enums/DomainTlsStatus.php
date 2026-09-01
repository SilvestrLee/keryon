<?php

namespace App\Enums;

enum DomainTlsStatus: string
{
    case NotStarted = 'not_started';
    case Provisioning = 'provisioning';
    case Ready = 'ready';
    case Failed = 'failed';
}
