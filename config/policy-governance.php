<?php

return [
    // No real destination exists yet (K-LEGAL-001B-B0). Leaving this unset
    // (the default) binds GovernanceEventDeliveryChannel to
    // UnavailableGovernanceEventDeliveryChannel, which never fabricates a
    // successful delivery. Only 'fake' (non-production only, for tests) is
    // currently a valid value; a real driver is a separately authorized
    // future decision — see K-LEGAL-001B-A-DECISION-ADDENDUM.md.
    'delivery_channel' => env('POLICY_GOVERNANCE_DELIVERY_CHANNEL'),
];
