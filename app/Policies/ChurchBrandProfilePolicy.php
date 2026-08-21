<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\ChurchBrandProfile;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * K-CHURCHWEB-001B §7/§34 — Brand Profile is institutional identity, not
 * Website-owned, so it authorizes against `church.identity.*`, not
 * `website.content.*`. See Capability::ChurchIdentityView/Manage.
 */
class ChurchBrandProfilePolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::ChurchIdentityView) ?? false;
    }

    public function view(User $user, ChurchBrandProfile $profile): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::ChurchIdentityView)
            && $profile->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::ChurchIdentityManage) ?? false;
    }

    public function update(User $user, ChurchBrandProfile $profile): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::ChurchIdentityManage)
            && $profile->church_id === $membership->church_id;
    }
}
