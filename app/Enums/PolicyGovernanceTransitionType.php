<?php

namespace App\Enums;

/**
 * Mirrors the three PolicyVersion lifecycle transitions defined in
 * K-LEGAL-001B-A-ARCHITECTURE.md §4.2. PolicyVersion itself is not built by
 * this milestone; this enum exists so the event schema is ready for it.
 */
enum PolicyGovernanceTransitionType: string
{
    case APPROVED = 'approved';
    case PUBLISHED = 'published';
    case RETIRED = 'retired';
}
