<?php

namespace App\Platform\Read\Dto;

final readonly class PlatformSubscriptionSummary
{
    /** @param list<array<string, scalar|null>> $invoices @param list<array<string, scalar|null>> $payments */
    public function __construct(
        public int $id, public string $uuid, public int $churchId, public string $churchName,
        public string $status, public ?string $trialStartedAt, public ?string $trialEndsAt,
        public ?string $periodStart, public ?string $periodEnd, public bool $cancelAtPeriodEnd,
        public ?string $planVersion, public ?string $market, public ?string $price,
        public ?string $currency, public ?int $amountMinor, public ?string $payerType,
        public ?string $payerName, public ?string $billingEmail, public array $invoices,
        public array $payments, public string $createdAt,
    ) {}
}
