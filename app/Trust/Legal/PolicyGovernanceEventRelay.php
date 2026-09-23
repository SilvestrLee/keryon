<?php

namespace App\Trust\Legal;

use App\Enums\PolicyGovernanceDeliveryStatus;
use App\Models\PolicyGovernanceEvent;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Minimal relay: attempts delivery of pending PolicyGovernanceEvent rows via
 * whichever GovernanceEventDeliveryChannel is bound, updating each event's
 * delivery-tracking fields. Never fabricates success. See
 * K-LEGAL-001B-A-DECISION-ADDENDUM.md §1/§3.
 *
 * Scheduling (a queued job, a cron entry) is a separate, later operational
 * decision this class does not make — it is directly callable, synchronously,
 * from whatever eventually invokes it.
 */
final class PolicyGovernanceEventRelay
{
    public function __construct(private readonly GovernanceEventDeliveryChannel $channel) {}

    /** @return array{attempted:int, delivered:int} */
    public function relayPending(int $limit = 50): array
    {
        $attempted = 0;
        $delivered = 0;

        PolicyGovernanceEvent::query()
            ->where('delivery_status', PolicyGovernanceDeliveryStatus::PENDING->value)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id'])
            ->each(function (PolicyGovernanceEvent $event) use (&$attempted, &$delivered): void {
                $attempted++;
                if ($this->relayOne($event)) {
                    $delivered++;
                }
            });

        return ['attempted' => $attempted, 'delivered' => $delivered];
    }

    /**
     * Two short, separately-committed locked sections — never one long one
     * spanning the external call. External I/O (GovernanceEventDeliveryChannel
     * ::deliver()) always runs OUTSIDE any open transaction/row lock:
     *
     *   1. Claim: lock the row, confirm it is still pending, record the
     *      attempt (increments delivery_attempts, sets last_attempted_at),
     *      commit, release the lock. Bounded by ordinary local database
     *      write latency only.
     *   2. Deliver: call the channel with no lock held at all.
     *   3. Record: lock the row again, confirm it is (still) pending, apply
     *      the outcome, commit. Also bounded by ordinary local write latency
     *      only.
     *
     * A real network-calling channel implementation can therefore hang or
     * time out without ever holding a database row lock for that duration —
     * see K-LEGAL-001B-A-DECISION-ADDENDUM.md's delivery safety contract.
     * Step 1's claim is not a strict mutual-exclusion guarantee against a
     * concurrent relay run also calling deliver() for the same event between
     * steps 1 and 3 — the delivery channel's own event_uuid-keyed idempotency
     * contract (see GovernanceEventDeliveryChannel) is what makes that safe
     * even if it happens; step 3's re-check of delivery_status discards a
     * late-arriving acknowledgement if another attempt already recorded one.
     */
    public function relayOne(PolicyGovernanceEvent $event): bool
    {
        $claimed = DB::transaction(function () use ($event): ?PolicyGovernanceEvent {
            $locked = PolicyGovernanceEvent::query()->whereKey($event->getKey())->lockForUpdate()->first();
            if (! $locked || $locked->delivery_status !== PolicyGovernanceDeliveryStatus::PENDING) {
                return null;
            }
            $locked->forceFill([
                'delivery_attempts' => $locked->delivery_attempts + 1,
                'last_attempted_at' => now(),
            ])->save();

            return $locked;
        });

        if ($claimed === null) {
            return false;
        }

        try {
            $acknowledgement = $this->channel->deliver($claimed);
        } catch (Throwable) {
            $acknowledgement = null;
        }

        // blank() covers null, '', and whitespace-only strings — a channel
        // returning any of these has not confirmed delivery. This is checked
        // here, defensively, rather than left for PolicyGovernanceEvent's own
        // model guard to reject: that guard throwing on a blank acknowledgement
        // is correct in principle, but letting it fire here would surface a
        // model-validation exception out of an ordinary "destination didn't
        // confirm" outcome instead of the event simply staying pending.
        if (blank($acknowledgement)) {
            return false; // the attempt above is already recorded; the event remains pending
        }

        return DB::transaction(function () use ($claimed, $acknowledgement): bool {
            $locked = PolicyGovernanceEvent::query()->whereKey($claimed->getKey())->lockForUpdate()->first();
            if (! $locked || $locked->delivery_status !== PolicyGovernanceDeliveryStatus::PENDING) {
                return false; // already recorded delivered by a concurrent attempt — this ack is discarded, safely
            }
            $locked->forceFill([
                'delivery_status' => PolicyGovernanceDeliveryStatus::DELIVERED->value,
                'external_acknowledgement_reference' => $acknowledgement,
                'delivered_at' => now(),
            ])->save();

            return true;
        });
    }
}
