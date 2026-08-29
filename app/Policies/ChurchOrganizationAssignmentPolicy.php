<?php

namespace App\Policies;

use App\Models\ChurchOrganizationAssignment;
use App\Models\Organization;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Organizations\OrganizationScopeResolver;
use App\Policies\Concerns\ResolvesOrganizationMembership;

class ChurchOrganizationAssignmentPolicy
{
    use ResolvesOrganizationMembership;

    public function view(User $user, ChurchOrganizationAssignment $assignment): bool
    {
        return $this->organizationMembershipFor($user)?->organization_id === $assignment->organization_id
            && app(OrganizationScopeResolver::class)->canViewUnit($assignment->unit);
    }

    public function requestAttachment(User $user, Organization $organization, OrganizationUnit $destination): bool
    {
        return $this->organizationMembershipFor($user)?->organization_id === $organization->id
            && $destination->organization_id === $organization->id
            && app(OrganizationScopeResolver::class)->canManageAttachmentDestination($destination);
    }

    public function move(User $user, ChurchOrganizationAssignment $assignment, OrganizationUnit $destination): bool
    {
        $resolver = app(OrganizationScopeResolver::class);

        return $this->organizationMembershipFor($user)?->organization_id === $assignment->organization_id
            && $destination->organization_id === $assignment->organization_id
            && $resolver->canManageChurchAssignment($assignment->church)
            && $resolver->canManageAttachmentDestination($destination);
    }

    public function detach(User $user, ChurchOrganizationAssignment $assignment): bool
    {
        return $this->organizationMembershipFor($user)?->organization_id === $assignment->organization_id
            && app(OrganizationScopeResolver::class)->canManageChurchAssignment($assignment->church);
    }
}
