<?php

namespace App\Support;

use App\Enums\PlatformCapability;
use App\Models\PlatformMembership;
use Illuminate\Support\Facades\Auth;

class PlatformContext
{
    protected bool $resolved = false;

    protected ?PlatformMembership $membership = null;

    protected false|null|int $resolvedForUserId = false;

    public function currentMembership(): ?PlatformMembership
    {
        $userId = Auth::check() ? Auth::id() : null;

        if (! $this->resolved || $this->resolvedForUserId !== $userId) {
            $this->membership = $userId === null
                ? null
                : PlatformMembership::query()->active()->where('user_id', $userId)->first();
            $this->resolved = true;
            $this->resolvedForUserId = $userId;
        }

        return $this->membership;
    }

    public function hasContext(): bool
    {
        return $this->currentMembership() !== null;
    }

    public function hasCapability(PlatformCapability $capability): bool
    {
        return $this->currentMembership()?->hasCapability($capability) ?? false;
    }

    public function forgetResolved(): void
    {
        $this->resolved = false;
        $this->membership = null;
        $this->resolvedForUserId = false;
    }
}
