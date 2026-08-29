<?php

namespace App\Enums;

enum MediaRenditionState: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
