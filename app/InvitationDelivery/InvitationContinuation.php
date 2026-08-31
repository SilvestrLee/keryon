<?php

namespace App\InvitationDelivery;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class InvitationContinuation
{
    public const TTL_MINUTES = 30;

    public function create(string $type, string $rawToken): string
    {
        $id = Str::random(48);
        session()->put($this->key($id), [
            'payload' => Crypt::encryptString(json_encode(['type' => $type, 'token' => $rawToken], JSON_THROW_ON_ERROR)),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->getTimestamp(),
        ]);

        return $id;
    }

    /** @return array{type:string,token:string}|null */
    public function get(string $id): ?array
    {
        $stored = session()->get($this->key($id));
        if (! is_array($stored) || ($stored['expires_at'] ?? 0) < now()->getTimestamp() || ! is_string($stored['payload'] ?? null)) {
            return null;
        }
        try {
            return json_decode(Crypt::decryptString($stored['payload']), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
    }

    public function forget(string $id): void
    {
        session()->forget($this->key($id));
    }

    private function key(string $id): string
    {
        return 'invitation-continuation:'.hash_hmac('sha256', $id, (string) config('app.key'));
    }
}
