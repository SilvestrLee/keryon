<?php

namespace App\Models;

use App\Enums\CheckoutIntentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CheckoutIntent extends Model
{
    protected $fillable = ['invoice_id', 'billing_account_id', 'provider', 'provider_account_key', 'provider_environment', 'local_reference', 'amount_minor', 'currency', 'status', 'provider_reference', 'authorization_url', 'access_code', 'expires_at'];

    protected function casts(): array
    {
        return ['status' => CheckoutIntentStatus::class, 'amount_minor' => 'integer', 'expires_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $intent) => $intent->uuid ??= (string) Str::uuid());
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }
}
