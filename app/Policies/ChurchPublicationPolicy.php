<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\ChurchPublication;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

class ChurchPublicationPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentView) ?? false;
    }

    public function view(User $user, ChurchPublication $publication): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteContentView)
            && $publication->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentManage) ?? false;
    }

    public function update(User $user, ChurchPublication $publication): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteContentManage)
            && $publication->church_id === $membership->church_id;
    }

    public function delete(User $user, ChurchPublication $publication): bool
    {
        return $this->update($user, $publication);
    }

    public function reorder(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentManage) ?? false;
    }
}
