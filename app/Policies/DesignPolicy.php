<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\Design;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

class DesignPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::DesignsView) ?? false;
    }

    public function view(User $user, Design $design): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::DesignsView)
            && $membership->church_id === $design->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::DesignsManage) ?? false;
    }

    public function update(User $user, Design $design): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::DesignsManage)
            && $membership->church_id === $design->church_id;
    }

    public function approve(User $user, Design $design): bool
    {
        return $this->update($user, $design);
    }
}
