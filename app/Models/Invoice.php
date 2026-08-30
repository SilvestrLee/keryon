<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Invoice extends Model
{
    protected $fillable = ['merchant_legal_entity_id', 'billing_account_id', 'replacement_invoice_id', 'invoice_number', 'status', 'currency', 'subtotal_minor', 'tax_minor', 'total_minor', 'amount_paid_minor', 'amount_due_minor', 'period_start', 'period_end', 'issued_at', 'due_at', 'paid_at', 'voided_at', 'issuance_key', 'billing_profile_snapshot', 'void_reason'];

    protected function casts(): array
    {
        return ['status' => InvoiceStatus::class, 'subtotal_minor' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer', 'amount_paid_minor' => 'integer', 'amount_due_minor' => 'integer', 'period_start' => 'datetime', 'period_end' => 'datetime', 'issued_at' => 'datetime', 'due_at' => 'datetime', 'paid_at' => 'datetime', 'voided_at' => 'datetime', 'billing_profile_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $i) => $i->uuid ??= (string) Str::uuid());
        static::updating(function (self $invoice): void {
            $original = InvoiceStatus::tryFrom((string) $invoice->getRawOriginal('status'));
            if ($original !== null && $original !== InvoiceStatus::DRAFT) {
                $allowed = ['status', 'amount_paid_minor', 'amount_due_minor', 'paid_at', 'voided_at', 'void_reason', 'replacement_invoice_id', 'updated_at'];
                if (array_diff(array_keys($invoice->getDirty()), $allowed)) {
                    throw new DomainException('Issued Invoice financial evidence is immutable.');
                }
            }
        });
        static::deleting(function (self $invoice): void {
            if ($invoice->status !== InvoiceStatus::DRAFT) {
                throw new DomainException('Issued Invoices cannot be deleted.');
            }
        });
    }

    public function merchantLegalEntity(): BelongsTo
    {
        return $this->belongsTo(MerchantLegalEntity::class);
    }

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
