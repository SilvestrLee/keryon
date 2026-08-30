<?php

namespace App\Enums;

enum PlanVersionStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case RETIRED = 'retired';

    public function isRuntimeEligible(): bool
    {
        return in_array($this, [self::ACTIVE, self::RETIRED], true);
    }
}
