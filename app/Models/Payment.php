<?php

namespace App\Models;

use App\Enums\PaymentSource;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected $fillable = ['billing_account_id', 'status', 'source', 'currency', 'amount_minor', 'received_at', 'failed_at', 'cancelled_at', 'failure_category', 'idempotency_key', 'manual_institution', 'manual_reference'];

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'source' => PaymentSource::class, 'amount_minor' => 'integer', 'received_at' => 'datetime', 'failed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $p) => $p->uuid ??= (string) Str::uuid());
    }

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function allocatedAmount(): int
    {
        return (int) $this->allocations()->sum('amount_minor');
    }
}
