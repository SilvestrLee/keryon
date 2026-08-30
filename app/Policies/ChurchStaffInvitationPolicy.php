<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\Church;
use App\Models\ChurchStaffInvitation;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

class ChurchStaffInvitationPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::StaffView) ?? false;
    }

    public function view(User $user, ChurchStaffInvitation $invitation): bool
    {
        $actor = $this->membershipFor($user);

        return $actor !== null && $actor->church_id === $invitation->church_id && $actor->hasCapability(Capability::StaffView);
    }

    public function create(User $user, Church $church): bool
    {
        $actor = $this->membershipFor($user);

        return $actor !== null && $actor->church_id === $church->id && $actor->hasCapability(Capability::StaffManage);
    }

    public function update(User $user, ChurchStaffInvitation $invitation): bool
    {
        $actor = $this->membershipFor($user);

        return $actor !== null && $actor->church_id === $invitation->church_id && $actor->hasCapability(Capability::StaffManage);
    }
}
