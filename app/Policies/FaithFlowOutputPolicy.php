<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\FaithFlowOutput;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * Mirrors FaithFlowRunPolicy/ContentItemPolicy — see K-FAITHFLOW-001B §34.
 * Generation-level abilities (generate/regenerate/approve) all delegate to
 * update(), matching how ContentItemPolicy's lifecycle abilities delegate
 * to its own update(). One `faithflow.use` capability, no finer split.
 */
class FaithFlowOutputPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::FaithflowUse) ?? false;
    }

    public function view(User $user, FaithFlowOutput $faithFlowOutput): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::FaithflowUse)
            && $faithFlowOutput->church_id === $membership->church_id;
    }

    public function update(User $user, FaithFlowOutput $faithFlowOutput): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::FaithflowUse)
            && $faithFlowOutput->church_id === $membership->church_id;
    }

    public function delete(User $user, FaithFlowOutput $faithFlowOutput): bool
    {
        return $this->update($user, $faithFlowOutput);
    }

    public function generate(User $user, FaithFlowOutput $faithFlowOutput): bool
    {
        return $this->update($user, $faithFlowOutput);
    }

    public function regenerate(User $user, FaithFlowOutput $faithFlowOutput): bool
    {
        return $this->update($user, $faithFlowOutput);
    }

    public function approve(User $user, FaithFlowOutput $faithFlowOutput): bool
    {
        return $this->update($user, $faithFlowOutput);
    }
}
