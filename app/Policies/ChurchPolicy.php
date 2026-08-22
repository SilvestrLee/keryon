<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\Church;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * K-CHURCHWEB-001C §21 — authorizes the Church-owned institutional
 * identity fields (name/email/phone/address) that Website (and other
 * Communications surfaces) consume. Deliberately gated on
 * `Capability::ChurchIdentityManage` (Communications), not
 * `Capability::ChurchManage` (Administrator-only, broader Church
 * governance settings such as timezone/active status) — the two remain
 * distinct capabilities for distinct concerns, per
 * K-CHURCHWEB-001B §17.
 */
class ChurchPolicy
{
    use ResolvesTenantMembership;

    public function view(User $user, Church $church): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::ChurchIdentityView)
            && $church->id === $membership->church_id;
    }

    public function update(User $user, Church $church): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::ChurchIdentityManage)
            && $church->id === $membership->church_id;
    }
}
