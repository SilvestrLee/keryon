<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * Closes the Care Center exposure identified in K-AUTH-001A §G/§H: without
 * this policy, Filament's non-strict authorization defaulted to allowing
 * any same-church authenticated user full access to Care data. Care is
 * explicit — see Keryon Blueprint v1.4.1 §10 — and must never be implied
 * by Primary, Administrator, or Communications responsibility alone.
 */
class PrayerRequestPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::CareView) ?? false;
    }

    public function view(User $user, PrayerRequest $prayerRequest): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::CareView)
            && $prayerRequest->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::CareManage) ?? false;
    }

    public function update(User $user, PrayerRequest $prayerRequest): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::CareManage)
            && $prayerRequest->church_id === $membership->church_id;
    }

    public function delete(User $user, PrayerRequest $prayerRequest): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::CareManage)
            && $prayerRequest->church_id === $membership->church_id;
    }
}
