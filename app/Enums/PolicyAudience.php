<?php

namespace App\Enums;

/**
 * A null `audience` column value means shared/either — see
 * K-LEGAL-001B-A-ARCHITECTURE.md §3.5. This is how the schema accommodates
 * either outcome of unresolved counsel question A10 without a future schema
 * change.
 */
enum PolicyAudience: string
{
    case PRIMARY = 'primary';
    case STAFF = 'staff';
}
