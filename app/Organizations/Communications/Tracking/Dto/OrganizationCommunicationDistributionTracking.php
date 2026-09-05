<?php

namespace App\Organizations\Communications\Tracking\Dto;

/**
 * K-ORG-COMMS-001F §23 — the bounded per-distribution tracking summary:
 * what was targeted, its processing state, and outcome counts across
 * exactly this distribution's own deliveries (no cross-distribution
 * de-duplication here — that only applies to the communication-level
 * summary, §21). No Church-local data.
 */
final class OrganizationCommunicationDistributionTracking
{
    /** @param  array<string, int>  $counts */
    public function __construct(
        public readonly int $distributionId,
        public readonly int $revisionVersion,
        public readonly string $targetSummary,
        public readonly string $state,
        public readonly string $stateLabel,
        public readonly ?string $snapshotAt,
        public readonly int $recipientCount,
        public readonly array $counts,
    ) {}

    public function count(string $outcome): int
    {
        return $this->counts[$outcome] ?? 0;
    }
}
