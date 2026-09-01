<?php

namespace App\Platform\Read\Dto;

final readonly class PlatformDomainSummary
{
    /** @param list<array{event:string,failure:?string,occurred_at:string}> $events */
    public function __construct(
        public int $id, public string $uuid, public int $churchId, public string $churchName,
        public string $hostname, public ?string $displayHostname, public bool $primary,
        public string $status, public bool $ownershipVerified, public bool $routingVerified,
        public string $tlsStatus, public ?string $failureCode, public int $failureCount,
        public ?string $lastCheckedAt, public ?string $releasedAt, public array $events,
        public string $createdAt,
    ) {}
}
