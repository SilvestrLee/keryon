<?php

namespace App\Communications\OrganizationInbox;

/**
 * K-ORG-COMMS-001D §9 — the bounded, read-only shape behind the Organization
 * Inbox list. Never carries a full material body, an Organization-internal
 * field, or a sibling-Church's data.
 */
final class ChurchOrganizationCommunicationSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $title,
        public readonly string $organizationName,
        public readonly string $kind,
        public readonly string $kindLabel,
        public readonly int $revisionVersion,
        public readonly string $availableAt,
        public readonly ?string $availableUntil,
        public readonly string $responseState,
        public readonly string $responseStateLabel,
        public readonly string $adaptationPolicyLabel,
    ) {}
}
