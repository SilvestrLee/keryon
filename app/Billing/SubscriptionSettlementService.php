<?php

namespace App\Billing;

use App\Enums\CommercialAuditEventType;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use DomainException;
use Illuminate\Support\Facades\DB;

class SubscriptionSettlementService
{
    public function convertTrialAfterSettlement(Subscription $subscription, Invoice $invoice, string $idempotencyKey, ?int $actorUserId, string $origin): Subscription
    {
        return $this->advance($subscription, $invoice, $idempotencyKey, $actorUserId, $origin, true);
    }

    public function renew(Subscription $subscription, Invoice $invoice, string $idempotencyKey, ?int $actorUserId, string $origin): Subscription
    {
        return $this->advance($subscription, $invoice, $idempotencyKey, $actorUserId, $origin, false);
    }

    private function advance(Subscription $subscription, Invoice $invoice, string $key, ?int $actor, string $origin, bool $trial): Subscription
    {
        if ($key === '' || $origin === '') {
            throw new DomainException('Settlement lifecycle requires idempotency and origin.');
        }

        return DB::transaction(function () use ($subscription, $invoice, $key, $actor, $origin, $trial): Subscription {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $bill = Invoice::query()->with('lines')->lockForUpdate()->findOrFail($invoice->id);
            if ($bill->status !== InvoiceStatus::PAID || $bill->lines->where('subscription_id', $locked->id)->isEmpty()) {
                throw new DomainException('A fully settled related Invoice is required.');
            }
            if ($bill->lines->where('subscription_id', '!=', $locked->id)->isNotEmpty()) {
                throw new DomainException('Lifecycle action requires an Invoice obligation unambiguous for this Subscription.');
            }
            $line = $bill->lines->where('subscription_id', $locked->id)->first();
            if ($line->plan_version_id !== $locked->item()->value('plan_version_id') || $line->price_id !== $locked->item()->value('price_id') || $bill->billing_account_id !== $locked->billing_account_id) {
                throw new DomainException('Invoice commercial identity does not match Subscription.');
            }
            if ($locked->current_period_end && $locked->current_period_end->equalTo($line->period_end)) {
                return $locked;
            }
            if ($trial && $locked->status !== SubscriptionStatus::TRIALING) {
                throw new DomainException('Only a trial may be converted.');
            }
            if (! $trial && $locked->status !== SubscriptionStatus::ACTIVE) {
                throw new DomainException('Only an active Subscription may renew.');
            }
            if (! $trial && $locked->current_period_end && ! $locked->current_period_end->equalTo($line->period_start)) {
                throw new DomainException('Renewal Invoice must cover the exact next period.');
            }
            $previous = ['status' => $locked->status->value, 'current_period_end' => $locked->current_period_end?->toIso8601String()];
            $locked->forceFill(['status' => SubscriptionStatus::ACTIVE, 'current_period_start' => $line->period_start, 'current_period_end' => $line->period_end])->save();
            $event = $trial ? CommercialAuditEventType::SUBSCRIPTION_TRIAL_CONVERTED : CommercialAuditEventType::SUBSCRIPTION_RENEWED;
            FinancialAudit::record($event, ['church_id' => $locked->church_id, 'billing_account_id' => $locked->billing_account_id, 'subscription_id' => $locked->id, 'invoice_id' => $bill->id], $actor, $previous, ['status' => 'active', 'current_period_start' => $line->period_start->toIso8601String(), 'current_period_end' => $line->period_end->toIso8601String(), 'idempotency_key' => $key, 'origin' => $origin]);

            return $locked;
        });
    }
}
