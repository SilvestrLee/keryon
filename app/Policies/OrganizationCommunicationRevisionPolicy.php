<?php

namespace App\Policies;

use App\Models\OrganizationCommunicationRevision;
use App\Models\User;

class OrganizationCommunicationRevisionPolicy
{
    public function view(User $user, OrganizationCommunicationRevision $revision): bool
    {
        return $user->can('view', $revision->communication);
    }

    public function update(User $user, OrganizationCommunicationRevision $revision): bool
    {
        return $user->can('update', $revision->communication);
    }

    public function submit(User $user, OrganizationCommunicationRevision $revision): bool
    {
        return $user->can('submit', $revision->communication);
    }

    public function approve(User $user, OrganizationCommunicationRevision $revision): bool
    {
        return $user->can('approve', $revision->communication);
    }
}
