<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\User;
use App\Models\WebsiteGivingContent;
use App\Policies\Concerns\ResolvesTenantMembership;

class WebsiteGivingContentPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentView) ?? false;
    }

    public function view(User $user, WebsiteGivingContent $content): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteContentView)
            && $content->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentManage) ?? false;
    }

    public function update(User $user, WebsiteGivingContent $content): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteContentManage)
            && $content->church_id === $membership->church_id;
    }
}
