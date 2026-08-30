<?php

namespace App\Models;

use App\Enums\CommercialAuditEventType;
use Illuminate\Database\Eloquent\Model;

class CommercialAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['event_type', 'church_id', 'billing_account_id', 'subscription_id', 'invoice_id', 'payment_id', 'actor_user_id', 'previous_state', 'new_state', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'event_type' => CommercialAuditEventType::class,
            'previous_state' => 'array',
            'new_state' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
