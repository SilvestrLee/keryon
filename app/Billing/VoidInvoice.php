<?php

namespace App\Billing;

use App\Enums\CommercialAuditEventType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use DomainException;
use Illuminate\Support\Facades\DB;

class VoidInvoice
{
    public function execute(Invoice $invoice, ?int $actorUserId, string $origin, string $reason): Invoice
    {
        if ($origin === '' || trim($reason) === '') {
            throw new DomainException('Voiding requires origin and reason.');
        }

        return DB::transaction(function () use ($invoice, $actorUserId, $origin, $reason): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->status !== InvoiceStatus::ISSUED || $locked->amount_paid_minor > 0) {
                throw new DomainException('Only an unpaid issued Invoice may be voided.');
            }
            $locked->forceFill(['status' => InvoiceStatus::VOID, 'voided_at' => now(), 'void_reason' => $reason])->save();
            FinancialAudit::record(CommercialAuditEventType::INVOICE_VOIDED, ['billing_account_id' => $locked->billing_account_id, 'invoice_id' => $locked->id], $actorUserId, ['status' => 'issued'], ['status' => 'void', 'reason' => $reason, 'origin' => $origin]);

            return $locked;
        });
    }
}
