<?php

namespace App\Policies;

use App\Models\OrganizationCommunicationMaterial;
use App\Models\User;

class OrganizationCommunicationMaterialPolicy
{
    public function view(User $user, OrganizationCommunicationMaterial $material): bool
    {
        return $user->can('view', $material->revision);
    }

    public function update(User $user, OrganizationCommunicationMaterial $material): bool
    {
        return $user->can('update', $material->revision);
    }

    public function delete(User $user, OrganizationCommunicationMaterial $material): bool
    {
        return $this->update($user, $material);
    }
}
