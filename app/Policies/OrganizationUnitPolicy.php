<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Organizations\OrganizationScopeResolver;
use App\Policies\Concerns\ResolvesOrganizationMembership;

class OrganizationUnitPolicy
{
    use ResolvesOrganizationMembership;

    public function view(User $user, OrganizationUnit $unit): bool
    {
        return $this->organizationMembershipFor($user) !== null
            && app(OrganizationScopeResolver::class)->canViewUnit($unit);
    }

    public function create(User $user, OrganizationUnit $parent): bool
    {
        return $this->organizationMembershipFor($user) !== null
            && app(OrganizationScopeResolver::class)->canManageUnit($parent);
    }

    public function update(User $user, OrganizationUnit $unit): bool
    {
        return $this->organizationMembershipFor($user) !== null
            && app(OrganizationScopeResolver::class)->canManageUnit($unit);
    }

    public function move(User $user, OrganizationUnit $unit, OrganizationUnit $destination): bool
    {
        $resolver = app(OrganizationScopeResolver::class);

        return $this->organizationMembershipFor($user) !== null
            && ! $resolver->isAssignedScopeRoot($unit, OrganizationCapability::UnitsManage)
            && $resolver->canManageUnit($unit)
            && $resolver->canManageUnit($destination);
    }

    public function archive(User $user, OrganizationUnit $unit): bool
    {
        return $this->update($user, $unit);
    }
}
