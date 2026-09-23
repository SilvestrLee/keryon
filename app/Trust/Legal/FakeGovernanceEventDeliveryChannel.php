<?php

namespace App\Trust\Legal;

use App\Models\PolicyGovernanceEvent;
use LogicException;

/**
 * Test double simulating a real, idempotent external destination: the same
 * event_uuid always yields the same acknowledgement reference, no matter how
 * many times delivery is attempted for it, exactly as a real idempotency-key
 * -aware destination would behave. Lets tests assert delivery-attempt
 * counts and that repeated attempts never produce a second, distinct
 * "external record" for the same event.
 */
final class FakeGovernanceEventDeliveryChannel implements GovernanceEventDeliveryChannel
{
    /** @var array<string,string> event_uuid => acknowledgement reference */
    private array $acknowledgements = [];

    /** @var list<string> event_uuid values, in call order, including duplicates */
    private array $deliveryAttempts = [];

    /** @var array<string,bool> event_uuid => whether delivery currently succeeds; defaults to true */
    private array $available = [];

    public function __construct()
    {
        // Allowlisted, not blocklisted: refuses everywhere except local/testing,
        // independently of the container-binding gate in AppServiceProvider —
        // both must agree, mirroring PolicyVersionSeeder's dual-check design
        // (K-LEGAL-001B-A-ARCHITECTURE.md §5.2). A single check being wrong is
        // not sufficient to make this class usable somewhere it shouldn't be.
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('The fake governance-event delivery channel may only run in local or testing environments.');
        }
    }

    public function markUnavailable(string $eventUuid): self
    {
        $this->available[$eventUuid] = false;

        return $this;
    }

    public function markAvailable(string $eventUuid): self
    {
        $this->available[$eventUuid] = true;

        return $this;
    }

    public function deliver(PolicyGovernanceEvent $event): ?string
    {
        $this->deliveryAttempts[] = $event->event_uuid;
        if (($this->available[$event->event_uuid] ?? true) === false) {
            return null;
        }

        return $this->acknowledgements[$event->event_uuid] ??= 'ack-'.$event->event_uuid;
    }

    public function attemptCountFor(string $eventUuid): int
    {
        return count(array_filter($this->deliveryAttempts, fn (string $id): bool => $id === $eventUuid));
    }

    public function acknowledgementFor(string $eventUuid): ?string
    {
        return $this->acknowledgements[$eventUuid] ?? null;
    }
}
