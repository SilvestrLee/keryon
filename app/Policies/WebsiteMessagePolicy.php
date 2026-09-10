<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\User;
use App\Models\WebsiteMessage;
use App\Policies\Concerns\ResolvesTenantMembership;

class WebsiteMessagePolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentView) ?? false;
    }

    public function view(User $user, WebsiteMessage $message): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteContentView)
            && $message->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentManage) ?? false;
    }

    public function update(User $user, WebsiteMessage $message): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteContentManage)
            && $message->church_id === $membership->church_id;
    }

    public function delete(User $user, WebsiteMessage $message): bool
    {
        return $this->update($user, $message);
    }
}
