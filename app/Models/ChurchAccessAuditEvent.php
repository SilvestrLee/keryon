<?php

namespace App\Models;

use App\Enums\ChurchAccessAuditEventType;
use Illuminate\Database\Eloquent\Model;

class ChurchAccessAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['church_id', 'event_type', 'subject_type', 'subject_id', 'actor_user_id', 'actor_membership_id', 'previous_state', 'new_state', 'occurred_at'];

    protected function casts(): array
    {
        return ['event_type' => ChurchAccessAuditEventType::class, 'previous_state' => 'array', 'new_state' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
