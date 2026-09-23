<?php

namespace App\Trust\Legal;

/**
 * Matches exactly the literal fixture strings hard-coded in
 * config/onboarding.php and config/staff.php. See
 * K-LEGAL-001B-A-ARCHITECTURE.md §5.1: any PolicyVersion using one of these
 * identifiers must be a synthetic fixture (is_synthetic_fixture = true) —
 * these strings can never be registered as a real, non-synthetic policy.
 */
final class ReservedSyntheticPolicyIdentifiers
{
    /** @var list<string> */
    public const array IDENTIFIERS = [
        'development-terms-v1', 'development-privacy-v1',
        'local-terms-fixture', 'local-privacy-fixture',
    ];
}
