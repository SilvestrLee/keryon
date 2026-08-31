<?php

namespace App\InvitationDelivery;

use DomainException;
use Illuminate\Support\Facades\RateLimiter;

final class InvitationDeliveryRateLimiter
{
    public function ensureSendAllowed(string $subjectKey, int $churchId, string $email): void
    {
        $recipient = hash_hmac('sha256', strtolower($email), (string) config('app.key'));
        $cooldown = "invite-send:cooldown:{$subjectKey}";
        $hourly = "invite-send:hour:{$subjectKey}";
        $daily = "invite-send:day:{$churchId}:{$recipient}";
        if (RateLimiter::tooManyAttempts($cooldown, 1) || RateLimiter::tooManyAttempts($hourly, 3) || RateLimiter::tooManyAttempts($daily, 10)) {
            throw new DomainException('Invitation delivery is temporarily unavailable. Please try again later.');
        }
        RateLimiter::hit($cooldown, 60);
        RateLimiter::hit($hourly, 3600);
        RateLimiter::hit($daily, 86400);
    }
}
