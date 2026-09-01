<?php

namespace App\Platform\Read\Dto;

final readonly class PlatformDeliverySummary
{
    public function __construct(
        public int $id, public string $uuid, public string $type, public ?int $churchId,
        public ?string $churchName, public string $recipient, public string $status,
        public int $attemptCount, public ?string $failureCategory, public ?string $providerReference,
        public ?string $providerAccount, public string $requestedAt, public ?string $queuedAt,
        public ?string $acceptedAt, public ?string $failedAt, public ?string $bouncedAt,
        public ?string $complainedAt,
    ) {}
}
