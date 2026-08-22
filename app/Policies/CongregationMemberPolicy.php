<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\CongregationMember;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * Converted from a flat `$user->church_id === $member->church_id` check to
 * capability-based authorization. Congregation access splits into read and
 * manage — Communications and Care may see who they serve without being
 * able to edit membership records; editing remains an Administrator
 * responsibility. See Keryon Blueprint v1.4.1 §9 and K-AUTH-001B §28.
 */
class CongregationMemberPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::CongregationView) ?? false;
    }

    public function view(User $user, CongregationMember $member): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::CongregationView)
            && $member->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::CongregationManage) ?? false;
    }

    public function update(User $user, CongregationMember $member): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::CongregationManage)
            && $member->church_id === $membership->church_id;
    }

    public function delete(User $user, CongregationMember $member): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::CongregationManage)
            && $member->church_id === $membership->church_id;
    }
}
