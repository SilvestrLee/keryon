<?php

namespace App\Organizations\Communications\Tracking\Dto;

/**
 * K-ORG-COMMS-001F §19/§21/§22/§77 — one bounded, factual outcome-count
 * summary for exactly one revision. A communication with multiple
 * distributed revisions gets one of these per revision — never merged
 * (§22: "Do not aggregate them into one ambiguous Church status").
 * Counts are already de-duplicated one-row-per-Church-per-this-revision
 * by the query that builds this (§21).
 */
final class OrganizationCommunicationTrackingSummary
{
    /**
     * @param  array<string, int>  $counts  keyed by outcome constant (see OrganizationCommunicationDeliveryOutcomeResolver)
     * @param  array<string, int>  $declineReasonCounts  keyed by OrganizationCommunicationDeclineReasonCode value
     */
    public function __construct(
        public readonly int $revisionId,
        public readonly int $revisionVersion,
        public readonly ?string $firstDistributedAt,
        public readonly int $recipientCount,
        public readonly array $counts,
        public readonly array $declineReasonCounts,
    ) {}

    public function count(string $outcome): int
    {
        return $this->counts[$outcome] ?? 0;
    }
}
