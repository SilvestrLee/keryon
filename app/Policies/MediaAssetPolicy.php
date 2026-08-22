<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\MediaAsset;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * K-CHURCHWEB-001B §12/§34 — institutional media authorizes against
 * `media.*`, not `website.content.*` — Website is one consumer among
 * future others (Design Studio, Campaigns).
 */
class MediaAssetPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::MediaView) ?? false;
    }

    public function view(User $user, MediaAsset $asset): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::MediaView)
            && $asset->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::MediaManage) ?? false;
    }

    public function update(User $user, MediaAsset $asset): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::MediaManage)
            && $asset->church_id === $membership->church_id;
    }

    public function delete(User $user, MediaAsset $asset): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::MediaManage)
            && $asset->church_id === $membership->church_id;
    }
}
