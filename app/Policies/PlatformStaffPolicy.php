<?php

namespace App\Policies;

use App\Enums\PlatformCapability;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Support\PlatformContext;

class PlatformStaffPolicy
{
    public function viewAny(User $user): bool
    {
        return app(PlatformContext::class)->hasCapability(PlatformCapability::PlatformStaffView);
    }

    public function create(User $user): bool
    {
        return app(PlatformContext::class)->hasCapability(PlatformCapability::PlatformStaffManage);
    }

    public function update(User $user, PlatformMembership $membership): bool
    {
        return app(PlatformContext::class)->hasCapability(PlatformCapability::PlatformStaffManage);
    }
}
