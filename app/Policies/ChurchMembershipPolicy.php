<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\ChurchMembership;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

class ChurchMembershipPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::StaffView) ?? false;
    }

    public function view(User $user, ChurchMembership $target): bool
    {
        $actor = $this->membershipFor($user);

        return $actor !== null && $actor->church_id === $target->church_id && $actor->hasCapability(Capability::StaffView);
    }

    public function update(User $user, ChurchMembership $target): bool
    {
        return $this->manage($user, $target);
    }

    public function suspend(User $user, ChurchMembership $target): bool
    {
        return $this->manage($user, $target);
    }

    public function reactivate(User $user, ChurchMembership $target): bool
    {
        return $this->manage($user, $target);
    }

    public function remove(User $user, ChurchMembership $target): bool
    {
        return $this->manage($user, $target);
    }

    private function manage(User $user, ChurchMembership $target): bool
    {
        $actor = $this->membershipFor($user);

        return $actor !== null && $actor->id !== $target->id && $actor->church_id === $target->church_id && $actor->hasCapability(Capability::StaffManage);
    }
}
