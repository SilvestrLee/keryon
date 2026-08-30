<?php

namespace App\Billing;

use App\Enums\CommercialAuditEventType;
use App\Enums\InvoiceStatus;
use App\Enums\MerchantLegalEntityStatus;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class IssueInvoice
{
    public function __construct(private readonly InvoiceNumberAllocator $numbers) {}

    public function execute(Invoice $invoice, ?int $actorUserId, string $origin, ?DateTimeInterface $issuedAt = null, ?DateTimeInterface $dueAt = null): Invoice
    {
        if ($origin === '') {
            throw new DomainException('Invoice issuance requires an explicit origin.');
        }
        $at = CarbonImmutable::instance($issuedAt ?? now());

        return DB::transaction(function () use ($invoice, $actorUserId, $origin, $at, $dueAt): Invoice {
            $locked = Invoice::query()->with(['lines', 'merchantLegalEntity', 'billingAccount.billingProfile'])->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->status !== InvoiceStatus::DRAFT || $locked->lines->isEmpty()) {
                throw new DomainException('Only a non-empty draft Invoice may be issued.');
            }
            if ($locked->merchantLegalEntity->status !== MerchantLegalEntityStatus::ACTIVE) {
                throw new DomainException('Inactive merchant entity cannot issue Invoices.');
            }
            if (! $locked->billingAccount->billingProfile) {
                throw new DomainException('Invoice issuance requires a current BillingProfile.');
            }
            if ($locked->lines->contains(fn ($line) => $line->currency !== $locked->currency) || $locked->lines->sum('total_minor') !== $locked->total_minor) {
                throw new DomainException('Invoice line currency and totals must be homogeneous.');
            }
            $number = $this->numbers->allocate($locked->merchantLegalEntity, $at);
            $locked->forceFill(['invoice_number' => $number, 'status' => InvoiceStatus::ISSUED, 'issued_at' => $at, 'due_at' => $dueAt ?? $at, 'billing_profile_snapshot' => $locked->billingAccount->billingProfile->snapshot()])->save();
            FinancialAudit::record(CommercialAuditEventType::INVOICE_ISSUED, ['billing_account_id' => $locked->billing_account_id, 'invoice_id' => $locked->id], $actorUserId, ['status' => 'draft'], ['status' => 'issued', 'invoice_number' => $number, 'origin' => $origin]);

            return $locked->fresh('lines');
        });
    }
}
