<?php

namespace App\Policies\Concerns;

use App\Models\ChurchMembership;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Resolves the active ChurchMembership for the acting User, defensively
 * confirming it belongs to that same User. Every Policy authorization
 * decision still states its own required capability and its own record
 * tenant-ownership check explicitly — this trait only removes the
 * repeated membership-resolution boilerplate, per K-AUTH-001B §22. It
 * must never hide what a Policy method actually requires.
 */
trait ResolvesTenantMembership
{
    /**
     * Null whenever there is no usable membership, or — defense in depth,
     * see K-AUTH-001B §21 — the resolved TenantContext membership does not
     * belong to the User argument the Policy method was invoked with.
     */
    protected function membershipFor(User $user): ?ChurchMembership
    {
        $membership = app(TenantContext::class)->currentMembership();

        if ($membership === null || $membership->user_id !== $user->id) {
            return null;
        }

        return $membership;
    }
}
