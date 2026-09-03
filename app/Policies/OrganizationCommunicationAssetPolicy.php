<?php

namespace App\Policies;

use App\Models\OrganizationCommunicationAsset;
use App\Models\User;

class OrganizationCommunicationAssetPolicy
{
    public function view(User $user, OrganizationCommunicationAsset $asset): bool
    {
        return $user->can('view', $asset->revision);
    }

    public function update(User $user, OrganizationCommunicationAsset $asset): bool
    {
        return $user->can('update', $asset->revision);
    }

    public function delete(User $user, OrganizationCommunicationAsset $asset): bool
    {
        return $this->update($user, $asset);
    }
}
