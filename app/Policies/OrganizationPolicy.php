<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Models\Organization;
use App\Models\User;
use App\Organizations\OrganizationScopeResolver;
use App\Policies\Concerns\ResolvesOrganizationMembership;

class OrganizationPolicy
{
    use ResolvesOrganizationMembership;

    public function view(User $user, Organization $organization): bool
    {
        $membership = $this->organizationMembershipFor($user);

        return $membership?->organization_id === $organization->id
            && app(OrganizationScopeResolver::class)->hasOrganizationCapability(OrganizationCapability::OrganizationView);
    }

    public function update(User $user, Organization $organization): bool
    {
        $membership = $this->organizationMembershipFor($user);

        return $membership?->organization_id === $organization->id
            && app(OrganizationScopeResolver::class)->hasRootCapability(OrganizationCapability::OrganizationManage);
    }
}
