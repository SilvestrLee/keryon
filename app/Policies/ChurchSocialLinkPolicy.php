<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\ChurchSocialLink;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * K-CHURCHWEB-001B §24/§34 — institutional, authorizes against
 * `church.identity.*`, not `website.content.*` (§22 ownership test).
 */
class ChurchSocialLinkPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::ChurchIdentityView) ?? false;
    }

    public function view(User $user, ChurchSocialLink $link): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::ChurchIdentityView)
            && $link->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::ChurchIdentityManage) ?? false;
    }

    public function update(User $user, ChurchSocialLink $link): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::ChurchIdentityManage)
            && $link->church_id === $membership->church_id;
    }

    public function delete(User $user, ChurchSocialLink $link): bool
    {
        return $this->update($user, $link);
    }
}
