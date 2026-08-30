<?php

namespace App\ChurchStaff;

use Illuminate\Support\Str;

final class ChurchStaffInvitationTokenService
{
    public const TTL_HOURS = 72;

    public static function generate(): string
    {
        return Str::random(64);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
