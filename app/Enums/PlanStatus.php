<?php

namespace App\Enums;

enum PlanStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case RETIRED = 'retired';
}
