<?php

namespace App\Onboarding;

use App\Enums\ChurchActivationStatus;
use App\Models\ChurchActivation;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChurchActivationTokenService
{
    public const TTL_HOURS = 72;

    public function issue(ChurchActivation $activation): string
    {
        return DB::transaction(function () use ($activation): string {
            $locked = ChurchActivation::query()->lockForUpdate()->findOrFail($activation->id);
            if ($locked->status !== ChurchActivationStatus::PENDING) {
                throw new DomainException('Only a pending activation with approved commercials may receive a token.');
            }
            $token = Str::random(64);
            $locked->forceFill([
                'token_hash' => self::hash($token),
                'token_expires_at' => CarbonImmutable::now()->addHours(self::TTL_HOURS),
                'invitation_sent_at' => now(),
            ])->save();

            return $token;
        });
    }

    public function revoke(ChurchActivation $activation): ChurchActivation
    {
        return DB::transaction(function () use ($activation): ChurchActivation {
            $locked = ChurchActivation::query()->lockForUpdate()->findOrFail($activation->id);
            if (! in_array($locked->status, [ChurchActivationStatus::PENDING, ChurchActivationStatus::COMMERCIAL_REVIEW], true)) {
                throw new DomainException('This activation can no longer be revoked.');
            }
            $locked->forceFill(['status' => ChurchActivationStatus::REVOKED, 'token_hash' => null, 'token_expires_at' => null, 'revoked_at' => now()])->save();

            return $locked;
        });
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
