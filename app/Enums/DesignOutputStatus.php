<?php

namespace App\Enums;

enum DesignOutputStatus: string
{
    case PENDING = 'pending';
    case RENDERED = 'rendered';
    case FAILED = 'failed';
}
