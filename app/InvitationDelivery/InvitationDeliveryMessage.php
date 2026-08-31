<?php

namespace App\InvitationDelivery;

final readonly class InvitationDeliveryMessage
{
    /** @param list<string> $roles */
    public function __construct(
        public string $recipient,
        public string $churchName,
        public string $url,
        public string $expiresAt,
        public array $roles = [],
        public bool $trackingDisabled = true,
    ) {}
}
