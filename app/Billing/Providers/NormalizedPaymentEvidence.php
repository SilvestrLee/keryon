<?php

namespace App\Billing\Providers;

use Carbon\CarbonImmutable;

final readonly class NormalizedPaymentEvidence
{
    public function __construct(public string $provider, public string $providerAccount, public string $environment, public string $providerTransactionReference, public string $providerTransactionId, public string $localReference, public string $status, public int $amountMinor, public string $currency, public CarbonImmutable $occurredAt, public string $payloadHash) {}
}
