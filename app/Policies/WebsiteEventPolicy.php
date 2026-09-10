<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\User;
use App\Models\WebsiteEvent;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * K-WEB-V1-001D-C §49 — reuses the pre-existing `website.content.*`
 * vocabulary, exactly like every other Website content model. No new
 * capability was introduced.
 */
class WebsiteEventPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentView) ?? false;
    }

    public function view(User $user, WebsiteEvent $event): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteContentView)
            && $event->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentManage) ?? false;
    }

    public function update(User $user, WebsiteEvent $event): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteContentManage)
            && $event->church_id === $membership->church_id;
    }

    public function delete(User $user, WebsiteEvent $event): bool
    {
        return $this->update($user, $event);
    }

    public function reorder(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentManage) ?? false;
    }
}
