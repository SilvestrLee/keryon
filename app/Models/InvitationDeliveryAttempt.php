<?php

namespace App\Models;

use App\Enums\InvitationDeliveryFailureCategory;
use App\Enums\InvitationDeliveryStatus;
use App\Enums\InvitationDeliverySubjectType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationDeliveryAttempt extends Model
{
    protected $fillable = [
        'uuid', 'subject_type', 'church_activation_id', 'church_staff_invitation_id',
        'recipient_email', 'status', 'token_fingerprint', 'sensitive_payload',
        'sensitive_payload_cleared_at', 'requested_at', 'queued_at', 'provider_accepted_at',
        'failed_at', 'bounced_at', 'complained_at', 'superseded_at', 'attempt_count',
        'failure_category', 'provider_message_reference', 'provider_account_key',
    ];

    protected $hidden = ['sensitive_payload', 'token_fingerprint'];

    protected function casts(): array
    {
        return [
            'subject_type' => InvitationDeliverySubjectType::class,
            'status' => InvitationDeliveryStatus::class,
            'failure_category' => InvitationDeliveryFailureCategory::class,
            'sensitive_payload' => 'encrypted:array',
            'requested_at' => 'immutable_datetime', 'queued_at' => 'immutable_datetime',
            'provider_accepted_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime',
            'bounced_at' => 'immutable_datetime', 'complained_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime', 'sensitive_payload_cleared_at' => 'immutable_datetime',
        ];
    }

    public function churchActivation(): BelongsTo
    {
        return $this->belongsTo(ChurchActivation::class);
    }

    public function churchStaffInvitation(): BelongsTo
    {
        return $this->belongsTo(ChurchStaffInvitation::class);
    }
}
