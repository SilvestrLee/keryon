<?php

namespace App\Platform\Security;

use App\Models\PlatformMembership;

final class PlatformMfaSession
{
    private const PREFIX = 'platform_mfa.';

    public function markVerified(PlatformMembership $membership): void
    {
        $credential = $membership->mfaCredential()->firstOrFail();
        session([
            self::PREFIX.'membership_id' => $membership->id,
            self::PREFIX.'credential_version' => $credential->credential_version,
            self::PREFIX.'verified_at' => now()->timestamp,
            self::PREFIX.'last_activity_at' => now()->timestamp,
            self::PREFIX.'password_signature' => $this->passwordSignature($membership),
        ]);
    }

    public function isSatisfied(PlatformMembership $membership): bool
    {
        $credential = $membership->mfaCredential;
        if (! $credential?->isUsable()) {
            return false;
        }

        $valid = (int) session(self::PREFIX.'membership_id') === $membership->id
            && (int) session(self::PREFIX.'credential_version') === $credential->credential_version
            && hash_equals((string) session(self::PREFIX.'password_signature'), $this->passwordSignature($membership))
            && (int) session(self::PREFIX.'verified_at') > now()->subHours((int) config('central.absolute_mfa_hours', 8))->timestamp
            && (int) session(self::PREFIX.'last_activity_at') > now()->subMinutes((int) config('central.idle_minutes', 30))->timestamp;

        if ($valid) {
            session([self::PREFIX.'last_activity_at' => now()->timestamp]);
        } else {
            $this->forget();
        }

        return $valid;
    }

    public function isFresh(PlatformMembership $membership): bool
    {
        return $this->isSatisfied($membership)
            && (int) session(self::PREFIX.'verified_at') > now()->subMinutes((int) config('central.fresh_mfa_minutes', 10))->timestamp;
    }

    public function forget(): void
    {
        session()->forget([
            self::PREFIX.'membership_id', self::PREFIX.'credential_version', self::PREFIX.'verified_at',
            self::PREFIX.'last_activity_at', self::PREFIX.'password_signature',
        ]);
    }

    private function passwordSignature(PlatformMembership $membership): string
    {
        return hash_hmac('sha256', $membership->user()->value('password'), (string) config('app.key'));
    }
}
