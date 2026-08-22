<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\FaithFlowOutput;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * Mirrors FaithFlowRunPolicy/ContentItemPolicy — see K-FAITHFLOW-001B §34.
 * Generation-level abilities (generate/regenerate/approve/edit) all delegate
 * to update(), matching how ContentItemPolicy's lifecycle abilities delegate
 * to its own update(). One `faithflow.use` capability, no finer split.
 *
 * K-FAITHFLOW-001E §26/§27: `approve` also covers the Content Studio
 * handoff for mapped output types (see ApproveFaithFlowOutput) — no
 * separate `handoff` ability. Handoff still requires the acting membership
 * to independently satisfy ContentItemPolicy::create() (Capability::
 * ContentManage) — this policy does not, and must not, imply that check.
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

    public function edit(User $user, FaithFlowOutput $faithFlowOutput): bool
    {
        return $this->update($user, $faithFlowOutput);
    }
}
