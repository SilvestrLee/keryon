<?php

namespace App\Policies;

use App\Models\OrganizationRoleAssignment;
use App\Models\User;

class OrganizationRoleAssignmentPolicy
{
    public function view(User $user, OrganizationRoleAssignment $assignment): bool
    {
        return $user->can('view', $assignment->membership);
    }

    public function update(User $user, OrganizationRoleAssignment $assignment): bool
    {
        return $user->can('update', $assignment->membership);
    }
}
