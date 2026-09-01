<?php

namespace App\Platform\Read\Dto;

final readonly class PlatformActivationSummary
{
    public function __construct(
        public int $id, public string $uuid, public int $churchId, public string $churchName,
        public string $primaryEmail, public string $status, public ?string $country,
        public ?string $market, public ?string $planVersion, public ?string $price,
        public string $billingInterval, public string $payerType, public ?string $expiresAt,
        public ?string $sentAt, public ?string $acceptedAt, public ?string $revokedAt,
        public ?string $deliveryStatus, public int $deliveryAttempts, public ?string $deliveryFailure,
        public string $origin, public string $operatorReference, public ?string $termsVersion,
        public ?string $privacyVersion, public string $createdAt,
    ) {}
}
