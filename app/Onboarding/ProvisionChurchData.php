<?php

namespace App\Onboarding;

use App\Enums\BillingAccountOwnerType;
use App\Enums\BillingInterval;

final readonly class ProvisionChurchData
{
    public function __construct(
        public string $operatorReference,
        public string $origin,
        public string $idempotencyKey,
        public string $churchName,
        public ?string $requestedSlug,
        public string $operatingCountryCode,
        public string $timezone,
        public string $prospectivePrimaryEmail,
        public BillingInterval $billingInterval,
        public BillingAccountOwnerType $payerType = BillingAccountOwnerType::CHURCH,
        public ?int $payerOrganizationId = null,
        public ?int $payerOrganizationUnitId = null,
        public ?int $organizationId = null,
        public ?int $organizationUnitId = null,
    ) {}
}
