<?php

namespace App\InvitationDelivery;

final class TokenGenerationFingerprint
{
    public static function make(string $tokenHash): string
    {
        return hash_hmac('sha256', $tokenHash, (string) config('app.key'));
    }
}
