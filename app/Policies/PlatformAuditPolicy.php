<?php

namespace App\Policies;

use App\Enums\PlatformCapability;
use App\Models\PlatformAuditEvent;
use App\Models\User;
use App\Support\PlatformContext;

class PlatformAuditPolicy
{
    public function viewAny(User $user): bool
    {
        return app(PlatformContext::class)->hasCapability(PlatformCapability::PlatformAuditView);
    }

    public function update(User $user, PlatformAuditEvent $event): bool
    {
        return false;
    }

    public function delete(User $user, PlatformAuditEvent $event): bool
    {
        return false;
    }
}
