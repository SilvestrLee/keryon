<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\OrganizationCommunicationAsset;
use App\Models\OrganizationCommunicationDelivery;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * K-ORG-COMMS-001D §3/§6 — Church-side authorization only. Organization-
 * side code never Gate-authorizes an individual delivery instance (it only
 * ever sees aggregate distribution counts on the Distribution model), so
 * this policy carries no Organization-context branch and no
 * OrganizationContext/OrganizationMembership check of any kind.
 *
 * Every method requires: an active TenantContext ChurchMembership
 * belonging to the acting User (never a synthetic membership, never an
 * OrganizationMembership or PlatformMembership), the relevant Church
 * capability, and `delivery.church_id` matching that membership's Church.
 * `OrganizationCommunicationDelivery` deliberately does not use
 * `BelongsToChurch` (§19), so this ownership check is explicit here rather
 * than automatic.
 */
class OrganizationCommunicationDeliveryPolicy
{
    use ResolvesTenantMembership;

    public function view(User $user, OrganizationCommunicationDelivery $delivery): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::OrganizationCommunicationsView)
            && (int) $delivery->church_id === (int) $membership->church_id;
    }

    public function respond(User $user, OrganizationCommunicationDelivery $delivery): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::OrganizationCommunicationsRespond)
            && (int) $delivery->church_id === (int) $membership->church_id;
    }

    /**
     * K-ORG-COMMS-001D §21-§24 — the Church-side asset boundary. An asset
     * is only ever reachable through a delivery the Church may view, and
     * only when that exact asset belongs to the exact revision the
     * delivery references — never merely "any asset from this
     * Organization".
     */
    public function viewAsset(User $user, OrganizationCommunicationDelivery $delivery, OrganizationCommunicationAsset $asset): bool
    {
        return $this->view($user, $delivery)
            && (int) $asset->organization_communication_revision_id === (int) $delivery->organization_communication_revision_id;
    }
}
