<?php

namespace App\Organizations\Communications\Tracking\Dto;

/**
 * K-ORG-COMMS-001F §16/§17/§40/§74 — one bounded recipient row. Contains
 * only Church-level factual outcome information — never a responder or
 * importer identity (§40/§72/§73), never a local record ID.
 */
final class OrganizationCommunicationChurchOutcome
{
    public function __construct(
        public readonly int $churchId,
        public readonly string $churchName,
        public readonly string $snapshotUnitPath,
        public readonly ?string $currentUnitPath,
        public readonly bool $isDetached,
        public readonly string $outcome,
        public readonly string $outcomeLabel,
        public readonly ?string $availableAt,
        public readonly ?string $respondedAt,
        public readonly ?string $declineReasonLabel,
        public readonly ?string $importedAt,
    ) {}
}
