<?php

namespace App\Policies\Concerns;

use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;

trait ResolvesOrganizationMembership
{
    protected function organizationMembershipFor(User $user): ?OrganizationMembership
    {
        $membership = app(OrganizationContext::class)->currentMembership();

        return $membership !== null && $membership->user_id === $user->id
            ? $membership
            : null;
    }
}
