<?php

namespace App\Billing;

use App\Models\Invoice;
use App\Models\Subscription;

class ConvertTrialAfterSettlement
{
    public function __construct(private readonly SubscriptionSettlementService $service) {}

    public function execute(Subscription $subscription, Invoice $invoice, string $idempotencyKey, ?int $actorUserId, string $origin): Subscription
    {
        return $this->service->convertTrialAfterSettlement($subscription, $invoice, $idempotencyKey, $actorUserId, $origin);
    }
}
