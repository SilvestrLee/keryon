<?php

namespace App\Enums;

enum PriceBookStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case RETIRED = 'retired';
}
