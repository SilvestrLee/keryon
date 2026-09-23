<?php

namespace App\Trust\Legal;

use App\Models\PolicyGovernanceEvent;

/**
 * The honest default: no genuinely independent, externally-retained
 * destination has been provisioned for policy governance evidence yet. This
 * implementation always leaves events pending rather than fabricating a
 * successful delivery — see K-LEGAL-001B-A-DECISION-ADDENDUM.md §3. It binds
 * by default (config/policy-governance.php) until a real driver is
 * separately authorized and configured.
 */
final class UnavailableGovernanceEventDeliveryChannel implements GovernanceEventDeliveryChannel
{
    public function deliver(PolicyGovernanceEvent $event): ?string
    {
        return null;
    }
}
