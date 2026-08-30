<?php

namespace App\Models;

use App\Enums\ChurchStaffInvitationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChurchStaffInvitation extends Model
{
    protected $fillable = [
        'uuid', 'church_id', 'email_normalized', 'prospective_user_id', 'status', 'pending_identity',
        'token_hash', 'token_expires_at', 'sent_at', 'accepted_at', 'revoked_at',
        'invited_by_membership_id', 'idempotency_key', 'acceptance_idempotency_key',
        'terms_version', 'privacy_version', 'legal_accepted_at', 'legal_accepted_by_user_id',
        'replaces_invitation_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChurchStaffInvitationStatus::class,
            'token_expires_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime',
            'legal_accepted_at' => 'immutable_datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function prospectiveUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prospective_user_id');
    }

    public function invitedByMembership(): BelongsTo
    {
        return $this->belongsTo(ChurchMembership::class, 'invited_by_membership_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(ChurchStaffInvitationRole::class, 'invitation_id');
    }

    public function replacementOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_invitation_id');
    }

    public static function pendingIdentity(int $churchId, string $email): string
    {
        return hash('sha256', $churchId.'|'.strtolower(trim($email)));
    }
}
