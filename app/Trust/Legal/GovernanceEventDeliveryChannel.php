<?php

namespace App\Trust\Legal;

use App\Models\PolicyGovernanceEvent;

/**
 * A destination that can acknowledge receipt of a PolicyGovernanceEvent.
 *
 * Implementations MUST use $event->event_uuid as an idempotency key when
 * calling the real destination, so that a retried delivery attempt for the
 * same event cannot create a second, duplicate record externally.
 *
 * Return the destination's acknowledgement/receipt reference ONLY once
 * delivery is confirmed. Return null — never throw — when the destination is
 * unavailable, unreachable, or delivery could not be confirmed; this is an
 * ordinary, expected outcome the relay handles by leaving the event pending
 * for a later retry, not a programming error. An unexpected exception is
 * treated by the relay identically to a null return (see
 * PolicyGovernanceEventRelay) — implementations do not need their own
 * try/catch purely to satisfy this contract, but should not rely on that.
 */
interface GovernanceEventDeliveryChannel
{
    public function deliver(PolicyGovernanceEvent $event): ?string;
}
