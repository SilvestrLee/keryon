<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\User;
use App\Models\WebsiteMinistry;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * K-CHURCHWEB-001B §19/§21/§34 — Website Content (repeatable structure),
 * authorizes against the pre-existing `website.content.*` vocabulary.
 */
class WebsiteMinistryPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentView) ?? false;
    }

    public function view(User $user, WebsiteMinistry $ministry): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteContentView)
            && $ministry->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentManage) ?? false;
    }

    public function update(User $user, WebsiteMinistry $ministry): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteContentManage)
            && $ministry->church_id === $membership->church_id;
    }

    public function delete(User $user, WebsiteMinistry $ministry): bool
    {
        return $this->update($user, $ministry);
    }

    public function reorder(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentManage) ?? false;
    }
}
