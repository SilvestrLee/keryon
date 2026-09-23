<?php

namespace App\Enums;

/**
 * See K-LEGAL-001B-A-ARCHITECTURE.md §4.1. draft -> approved -> retired, no
 * other transition, ever. As of K-LEGAL-001B-B1A, no PolicyVersion row can
 * legitimately leave DRAFT at all — approve()/publish()/retire() are a
 * separately authorized future milestone (see PolicyVersion's class
 * docblock).
 */
enum PolicyVersionStatus: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case RETIRED = 'retired';
}
