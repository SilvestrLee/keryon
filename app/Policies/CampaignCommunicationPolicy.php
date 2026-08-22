<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\CampaignCommunication;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

class CampaignCommunicationPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::CampaignsView) ?? false;
    }

    public function view(User $user, CampaignCommunication $communication): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::CampaignsView)
            && $communication->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::CampaignsManage) ?? false;
    }

    public function update(User $user, CampaignCommunication $communication): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::CampaignsManage)
            && $communication->church_id === $membership->church_id;
    }

    public function delete(User $user, CampaignCommunication $communication): bool
    {
        return $this->update($user, $communication);
    }

    public function restore(User $user, CampaignCommunication $communication): bool
    {
        return $this->update($user, $communication);
    }
}
