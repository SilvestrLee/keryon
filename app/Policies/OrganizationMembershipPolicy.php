<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Organizations\OrganizationScopeResolver;
use App\Policies\Concerns\ResolvesOrganizationMembership;

class OrganizationMembershipPolicy
{
    use ResolvesOrganizationMembership;

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->organizationMembershipFor($user)?->organization_id === $organization->id
            && app(OrganizationScopeResolver::class)->hasRootCapability(OrganizationCapability::MembershipsView);
    }

    public function view(User $user, OrganizationMembership $membership): bool
    {
        return $this->viewAny($user, $membership->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->organizationMembershipFor($user)?->organization_id === $organization->id
            && app(OrganizationScopeResolver::class)->hasRootCapability(OrganizationCapability::MembershipsManage);
    }

    public function update(User $user, OrganizationMembership $membership): bool
    {
        return $this->create($user, $membership->organization);
    }
}
