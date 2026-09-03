<?php

namespace App\Organizations\Communications;

use App\Enums\OrganizationCapability;
use App\Models\OrganizationCommunication;
use App\Models\OrganizationMembership;
use App\Models\OrganizationUnit;
use App\Organizations\OrganizationScopeResolver;
use App\Support\OrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;

class OrganizationCommunicationAuthorizer
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly OrganizationScopeResolver $scope,
    ) {}

    public function forUnit(OrganizationCapability $capability, OrganizationUnit $unit): OrganizationMembership
    {
        $membership = $this->freshMembership();

        if ($membership->organization_id !== $unit->organization_id
            || ! $this->scope->hasCapabilityForUnit($capability, $unit)) {
            throw new AuthorizationException('You are not authorized for Organization communications in this Unit scope.');
        }

        return $membership;
    }

    public function forCommunication(
        OrganizationCapability $capability,
        OrganizationCommunication $communication,
    ): OrganizationMembership {
        $membership = $this->freshMembership();
        $unit = OrganizationUnit::query()->findOrFail($communication->governing_unit_id);

        if ($membership->organization_id !== $communication->organization_id
            || $unit->organization_id !== $communication->organization_id
            || ! $this->scope->hasCapabilityForUnit($capability, $unit)) {
            throw new AuthorizationException('You are not authorized to act on this Organization communication.');
        }

        return $membership;
    }

    private function freshMembership(): OrganizationMembership
    {
        $this->context->forgetResolved();
        $membership = $this->context->currentMembership();

        if ($membership === null) {
            throw new AuthorizationException('An active Organization workspace is required.');
        }

        return $membership;
    }
}
