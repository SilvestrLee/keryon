<?php

namespace App\Enums;

enum TaxBehavior: string
{
    case UNSPECIFIED = 'unspecified';
    case INCLUSIVE = 'inclusive';
    case EXCLUSIVE = 'exclusive';
}
