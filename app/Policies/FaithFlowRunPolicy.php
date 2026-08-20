<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\FaithFlowRun;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * Mirrors ContentItemPolicy's shape exactly — see K-FAITHFLOW-001B §3.2/§30/
 * §34. One `faithflow.use` capability authorizes the whole FaithFlow
 * surface; no per-action capability split (submitForReview/approve/etc. on
 * ContentItem is the precedent this follows).
 */
class FaithFlowRunPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::FaithflowUse) ?? false;
    }

    public function view(User $user, FaithFlowRun $faithFlowRun): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::FaithflowUse)
            && $faithFlowRun->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::FaithflowUse) ?? false;
    }

    public function update(User $user, FaithFlowRun $faithFlowRun): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::FaithflowUse)
            && $faithFlowRun->church_id === $membership->church_id;
    }

    public function delete(User $user, FaithFlowRun $faithFlowRun): bool
    {
        return $this->update($user, $faithFlowRun);
    }

    public function analyze(User $user, FaithFlowRun $faithFlowRun): bool
    {
        return $this->update($user, $faithFlowRun);
    }
}
