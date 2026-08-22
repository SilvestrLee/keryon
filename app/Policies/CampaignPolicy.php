<?php

namespace App\Policies;

use App\Enums\CampaignStatus;
use App\Enums\Capability;
use App\Models\Campaign;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

class CampaignPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::CampaignsView) ?? false;
    }

    public function view(User $user, Campaign $campaign): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::CampaignsView)
            && $campaign->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::CampaignsManage) ?? false;
    }

    public function update(User $user, Campaign $campaign): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::CampaignsManage)
            && $campaign->church_id === $membership->church_id;
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->update($user, $campaign) && $campaign->status === CampaignStatus::DRAFT;
    }

    public function restore(User $user, Campaign $campaign): bool
    {
        return $this->update($user, $campaign);
    }
}
