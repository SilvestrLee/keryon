<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\MarketplaceItem;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

class MarketplaceItemPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::DesignsView) ?? false;
    }

    public function view(User $user, MarketplaceItem $item): bool
    {
        return $this->viewAny($user);
    }

    public function acquire(User $user, MarketplaceItem $item): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::DesignsView)
            && $membership->hasCapability(Capability::DesignsManage);
    }

    public function download(User $user, MarketplaceItem $item): bool
    {
        return $this->acquire($user, $item);
    }
}
