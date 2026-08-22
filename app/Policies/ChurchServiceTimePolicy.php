<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\ChurchServiceTime;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * K-CHURCHWEB-001B §23/§34 — institutional, authorizes against
 * `church.identity.*`, not `website.content.*` (§22 ownership test).
 */
class ChurchServiceTimePolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::ChurchIdentityView) ?? false;
    }

    public function view(User $user, ChurchServiceTime $serviceTime): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::ChurchIdentityView)
            && $serviceTime->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::ChurchIdentityManage) ?? false;
    }

    public function update(User $user, ChurchServiceTime $serviceTime): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::ChurchIdentityManage)
            && $serviceTime->church_id === $membership->church_id;
    }

    public function delete(User $user, ChurchServiceTime $serviceTime): bool
    {
        return $this->update($user, $serviceTime);
    }
}
