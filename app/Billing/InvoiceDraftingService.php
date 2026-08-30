<?php

namespace App\Billing;

use App\Billing\ValueObjects\ServicePeriod;
use App\Enums\CommercialAuditEventType;
use App\Enums\InvoiceStatus;
use App\Models\BillingAccount;
use App\Models\Invoice;
use App\Models\MerchantLegalEntity;
use App\Models\Subscription;
use DomainException;
use Illuminate\Support\Facades\DB;

class InvoiceDraftingService
{
    /** @param array<int, array{subscription: Subscription, period: ServicePeriod}> $obligations */
    public function draft(BillingAccount $payer, MerchantLegalEntity $entity, string $currency, array $obligations, string $issuanceKey, ?int $actorUserId, string $origin): Invoice
    {
        $currency = strtoupper($currency);
        if ($origin === '' || ! preg_match('/^[A-Z]{3}$/', $currency) || $obligations === []) {
            throw new DomainException('Invoice drafting requires origin, currency, and obligations.');
        }

        return DB::transaction(function () use ($payer, $entity, $currency, $obligations, $issuanceKey, $actorUserId, $origin): Invoice {
            if ($existing = Invoice::query()->where('issuance_key', $issuanceKey)->first()) {
                return $existing;
            }
            $starts = [];
            $ends = [];
            $invoice = Invoice::query()->create(['merchant_legal_entity_id' => $entity->id, 'billing_account_id' => $payer->id, 'status' => InvoiceStatus::DRAFT, 'currency' => $currency, 'period_start' => $obligations[0]['period']->start, 'period_end' => $obligations[0]['period']->end, 'issuance_key' => $issuanceKey]);
            $subtotal = 0;
            foreach ($obligations as $obligation) {
                $subscription = Subscription::query()->with(['item.price.priceBook.market', 'item.planVersion'])->findOrFail($obligation['subscription']->id);
                $period = $obligation['period'];
                $item = $subscription->item;
                $price = $item->price;
                if ($subscription->billing_account_id !== $payer->id || $price->currency !== $currency || $subscription->pricing_market_id !== $price->priceBook->pricing_market_id) {
                    throw new DomainException('Invoice obligations must share payer, market provenance, and currency.');
                }
                $lineKey = $item->id.'-'.$period->key();
                $lineSubtotal = $price->amount_minor * $item->quantity;
                $invoice->lines()->create(['church_id' => $subscription->church_id, 'subscription_id' => $subscription->id, 'subscription_item_id' => $item->id, 'plan_version_id' => $item->plan_version_id, 'price_id' => $price->id, 'pricing_market_id' => $subscription->pricing_market_id, 'billing_interval' => $price->billing_interval, 'period_start' => $period->start, 'period_end' => $period->end, 'description' => $item->planVersion->plan->name.' '.$item->planVersion->version_code, 'quantity' => $item->quantity, 'currency' => $currency, 'unit_amount_minor' => $price->amount_minor, 'subtotal_minor' => $lineSubtotal, 'tax_minor' => 0, 'total_minor' => $lineSubtotal, 'idempotency_key' => $lineKey]);
                $subtotal += $lineSubtotal;
                $starts[] = $period->start;
                $ends[] = $period->end;
            }
            $invoice->forceFill(['subtotal_minor' => $subtotal, 'tax_minor' => 0, 'total_minor' => $subtotal, 'amount_due_minor' => $subtotal, 'period_start' => collect($starts)->min(), 'period_end' => collect($ends)->max()])->save();
            FinancialAudit::record(CommercialAuditEventType::INVOICE_DRAFTED, ['billing_account_id' => $payer->id, 'invoice_id' => $invoice->id], $actorUserId, null, ['origin' => $origin, 'total_minor' => $subtotal, 'currency' => $currency]);

            return $invoice->fresh('lines');
        });
    }
}
