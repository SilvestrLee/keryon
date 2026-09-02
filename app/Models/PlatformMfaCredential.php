<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformMfaCredential extends Model
{
    protected $fillable = ['platform_membership_id', 'totp_secret', 'confirmed_at', 'recovery_code_hashes', 'recovery_codes_generated_at', 'invalidated_at', 'credential_version'];

    protected $hidden = ['totp_secret', 'recovery_code_hashes'];

    protected function casts(): array
    {
        return [
            'totp_secret' => 'encrypted',
            'recovery_code_hashes' => 'array',
            'confirmed_at' => 'immutable_datetime',
            'recovery_codes_generated_at' => 'immutable_datetime',
            'invalidated_at' => 'immutable_datetime',
            'credential_version' => 'integer',
        ];
    }

    public function platformMembership(): BelongsTo
    {
        return $this->belongsTo(PlatformMembership::class);
    }

    public function isUsable(): bool
    {
        return filled($this->totp_secret) && $this->confirmed_at !== null && $this->invalidated_at === null;
    }
}
