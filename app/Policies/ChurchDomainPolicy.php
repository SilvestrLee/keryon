<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\ChurchDomain;
use App\Models\ChurchMembership;
use App\Models\User;
use App\Support\TenantContext;

class ChurchDomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->governingMembership($user) !== null;
    }

    public function view(User $user, ChurchDomain $domain): bool
    {
        return $this->governingMembership($user)?->church_id === $domain->church_id;
    }

    public function create(User $user): bool
    {
        return $this->governingMembership($user) !== null;
    }

    public function update(User $user, ChurchDomain $domain): bool
    {
        return $this->view($user, $domain);
    }

    public function delete(User $user, ChurchDomain $domain): bool
    {
        return false;
    }

    private function governingMembership(User $user): ?ChurchMembership
    {
        $membership = app(TenantContext::class)->currentMembership();

        return $membership !== null
            && $membership->user_id === $user->getKey()
            && $membership->is_primary
            && $membership->hasCapability(Capability::WebsiteDomainManage)
                ? $membership
                : null;
    }
}
