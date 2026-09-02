<?php

namespace App\Platform\Security;

use App\Enums\PlatformAuditEventType;
use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformAuditTargetType;
use App\Enums\PlatformCapability;
use App\Models\PlatformMembership;
use App\Models\PlatformMfaCredential;
use App\Platform\PlatformAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use SensitiveParameter;

final class PlatformMfaCredentialService
{
    public function secretFor(PlatformMembership $membership): ?string
    {
        $credential = $membership->mfaCredential;

        return $credential?->isUsable() ? $credential->totp_secret : null;
    }

    public function beginEnrollment(PlatformMembership $membership, #[SensitiveParameter] string $secret): void
    {
        DB::transaction(function () use ($membership, $secret): void {
            $credential = PlatformMfaCredential::query()->lockForUpdate()->firstOrNew(['platform_membership_id' => $membership->id]);
            if ($credential->exists) {
                $credential->credential_version++;
            }
            $credential->forceFill(['totp_secret' => $secret, 'confirmed_at' => null, 'recovery_code_hashes' => null, 'recovery_codes_generated_at' => null, 'invalidated_at' => null])->save();
        });
    }

    /** @param list<string> $hashes */
    public function saveRecoveryHashes(PlatformMembership $membership, #[SensitiveParameter] array $hashes): void
    {
        $existing = $membership->mfaCredential()->first();
        if ($existing?->isUsable()) {
            $existing->forceFill(['recovery_code_hashes' => $hashes])->save();

            return;
        }

        DB::transaction(function () use ($membership, $hashes): void {
            $credential = PlatformMfaCredential::query()->where('platform_membership_id', $membership->id)->lockForUpdate()->firstOrFail();
            $credential->forceFill(['confirmed_at' => now(), 'recovery_code_hashes' => $hashes, 'recovery_codes_generated_at' => now(), 'invalidated_at' => null])->save();
            PlatformAudit::record(PlatformAuditEventType::PLATFORM_MFA_ENROLLED, PlatformAuditTargetType::PLATFORM_MFA_CREDENTIAL, $credential->id, $membership, null, ['status' => 'confirmed', 'credential_version' => $credential->credential_version, 'recovery_code_count' => count($hashes), 'actor_role' => $membership->role->value, 'capability' => null, 'result' => 'enrolled'], PlatformAuditReasonCategory::PLATFORM_ADMINISTRATION);
        });
        app(PlatformMfaSession::class)->markVerified($membership->fresh('mfaCredential'));
    }

    /** @param list<string> $plaintextCodes */
    public function regenerateRecoveryCodes(PlatformMembership $membership, #[SensitiveParameter] array $plaintextCodes): void
    {
        abort_unless(app(PlatformMfaSession::class)->isFresh($membership), 403, 'Fresh multi-factor authentication is required.');
        DB::transaction(function () use ($membership, $plaintextCodes): void {
            $credential = PlatformMfaCredential::query()->where('platform_membership_id', $membership->id)->lockForUpdate()->firstOrFail();
            abort_unless($credential->isUsable(), 409, 'Multi-factor authentication must be enrolled.');
            $credential->forceFill(['recovery_code_hashes' => array_map(fn (string $code): string => Hash::make($code), $plaintextCodes), 'recovery_codes_generated_at' => now(), 'credential_version' => $credential->credential_version + 1])->save();
            PlatformAudit::record(PlatformAuditEventType::PLATFORM_MFA_RECOVERY_REGENERATED, PlatformAuditTargetType::PLATFORM_MFA_CREDENTIAL, $credential->id, $membership, ['status' => 'confirmed', 'credential_version' => $credential->credential_version - 1, 'recovery_code_count' => null], ['status' => 'confirmed', 'credential_version' => $credential->credential_version, 'recovery_code_count' => count($plaintextCodes), 'actor_role' => $membership->role->value, 'capability' => null, 'result' => 'regenerated'], PlatformAuditReasonCategory::SECURITY_RESPONSE);
        });
        app(PlatformMfaSession::class)->markVerified($membership->fresh('mfaCredential'));
    }

    public function reset(PlatformMembership $target, PlatformMembership $actor, #[SensitiveParameter] string $password, PlatformAuditReasonCategory $reason, string $note, ?string $correlationId = null): void
    {
        abort_unless($actor->hasCapability(PlatformCapability::PlatformMfaReset), 403);
        $reauthKey = 'central-reauth:'.$actor->user_id.':'.request()->ip();
        abort_if(RateLimiter::tooManyAttempts($reauthKey, 5), 429, 'Too many reauthorization attempts. Try again later.');
        if (! Hash::check($password, $actor->user()->value('password'))) {
            RateLimiter::hit($reauthKey, 60);
            abort(403, 'Password confirmation failed.');
        }
        RateLimiter::clear($reauthKey);
        abort_unless(app(PlatformMfaSession::class)->isFresh($actor), 403, 'Fresh multi-factor authentication is required.');
        abort_if(trim($note) === '', 422, 'A reason note is required.');

        DB::transaction(function () use ($target, $actor, $reason, $note, $correlationId): void {
            $credential = PlatformMfaCredential::query()->where('platform_membership_id', $target->id)->lockForUpdate()->firstOrFail();
            $previousVersion = $credential->credential_version;
            $credential->forceFill(['totp_secret' => null, 'confirmed_at' => null, 'recovery_code_hashes' => null, 'recovery_codes_generated_at' => null, 'invalidated_at' => now(), 'credential_version' => $previousVersion + 1])->save();
            PlatformAudit::record(PlatformAuditEventType::PLATFORM_MFA_RESET, PlatformAuditTargetType::PLATFORM_MFA_CREDENTIAL, $credential->id, $actor, ['status' => 'confirmed', 'credential_version' => $previousVersion, 'recovery_code_count' => null], ['status' => 'invalidated', 'credential_version' => $credential->credential_version, 'recovery_code_count' => 0, 'actor_role' => $actor->role->value, 'capability' => PlatformCapability::PlatformMfaReset->value, 'result' => 'reset'], $reason, $note, $correlationId ?? (string) Str::uuid());
        });

        if ($target->id === $actor->id) {
            app(PlatformMfaSession::class)->forget();
        }
    }
}
