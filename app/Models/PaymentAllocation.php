<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'invoice_id', 'amount_minor', 'allocated_at', 'actor_user_id', 'origin', 'idempotency_key'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'allocated_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
