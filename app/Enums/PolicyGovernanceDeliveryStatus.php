<?php

namespace App\Enums;

/**
 * Deliberately two states only. There is no terminal "failed" state — an
 * event that cannot currently be delivered stays Pending and is retried
 * indefinitely, never abandoned, since it is durable evidence, not a
 * best-effort notification. See K-LEGAL-001B-A-DECISION-ADDENDUM.md §1.
 */
enum PolicyGovernanceDeliveryStatus: string
{
    case PENDING = 'pending';
    case DELIVERED = 'delivered';
}
