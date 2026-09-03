<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationCommunicationState;
use App\Models\OrganizationCommunication;
use App\Models\OrganizationMembership;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Organizations\OrganizationScopeResolver;
use App\Support\OrganizationContext;

class OrganizationCommunicationPolicy
{
    public function viewAny(User $user): bool
    {
        $membership = $this->freshMembership($user);

        return $membership?->hasCapability(OrganizationCapability::CommunicationsView) ?? false;
    }

    public function view(User $user, OrganizationCommunication $communication): bool
    {
        return $this->allows($user, $communication, OrganizationCapability::CommunicationsView, includeArchivedUnit: true);
    }

    public function create(User $user, OrganizationUnit $unit): bool
    {
        $membership = $this->freshMembership($user);

        return $membership?->organization_id === $unit->organization_id
            && app(OrganizationScopeResolver::class)->hasCapabilityForUnit(OrganizationCapability::CommunicationsCreate, $unit);
    }

    public function update(User $user, OrganizationCommunication $communication): bool
    {
        return $this->allows($user, $communication, OrganizationCapability::CommunicationsEdit);
    }

    public function submit(User $user, OrganizationCommunication $communication): bool
    {
        return $this->update($user, $communication);
    }

    public function approve(User $user, OrganizationCommunication $communication): bool
    {
        return $this->allows($user, $communication, OrganizationCapability::CommunicationsApprove);
    }

    public function withdraw(User $user, OrganizationCommunication $communication): bool
    {
        return $this->allows($user, $communication, OrganizationCapability::CommunicationsWithdraw);
    }

    public function close(User $user, OrganizationCommunication $communication): bool
    {
        return $this->withdraw($user, $communication);
    }

    public function delete(User $user, OrganizationCommunication $communication): bool
    {
        return $communication->state === OrganizationCommunicationState::DRAFT
            && $this->update($user, $communication);
    }

    private function allows(
        User $user,
        OrganizationCommunication $communication,
        OrganizationCapability $capability,
        bool $includeArchivedUnit = false,
    ): bool {
        $membership = $this->freshMembership($user);
        $unit = OrganizationUnit::query()->find($communication->governing_unit_id);

        return $membership !== null
            && $unit !== null
            && $membership->organization_id === $communication->organization_id
            && $unit->organization_id === $communication->organization_id
            && app(OrganizationScopeResolver::class)->hasCapabilityForUnit($capability, $unit, $includeArchivedUnit);
    }

    private function freshMembership(User $user): ?OrganizationMembership
    {
        $context = app(OrganizationContext::class);
        $context->forgetResolved();
        $membership = $context->currentMembership();

        return $membership?->user_id === $user->id ? $membership : null;
    }
}
