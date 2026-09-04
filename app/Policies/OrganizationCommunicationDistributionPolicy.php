<?php

namespace App\Policies;

use App\Models\OrganizationCommunicationDistribution;
use App\Models\User;

class OrganizationCommunicationDistributionPolicy
{
    public function view(User $user, OrganizationCommunicationDistribution $distribution): bool
    {
        return $user->can('viewDistributions', $distribution->communication);
    }
}
