<?php

namespace App\Organizations;

use App\Models\Church;
use App\Models\ChurchOrganizationAssignment;
use App\Models\Organization;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AuthorizedChurchAssignmentAction
{
    public function __construct(private readonly OrganizationHierarchyService $hierarchy) {}

    public function request(User $actor, Organization $organization, OrganizationUnit $unit, Church $church): ChurchOrganizationAssignment
    {
        Gate::forUser($actor)->authorize('requestAttachment', [ChurchOrganizationAssignment::class, $organization, $unit]);

        return $this->hierarchy->attachChurch($organization, $unit, $church, $actor->id);
    }

    public function move(User $actor, ChurchOrganizationAssignment $assignment, OrganizationUnit $destination): ChurchOrganizationAssignment
    {
        Gate::forUser($actor)->authorize('move', [$assignment, $destination]);

        return $this->hierarchy->moveChurch($assignment->church, $destination, $actor->id);
    }

    public function detach(User $actor, ChurchOrganizationAssignment $assignment, ?string $reason = null): ChurchOrganizationAssignment
    {
        Gate::forUser($actor)->authorize('detach', $assignment);

        return $this->hierarchy->detachChurch($assignment->church, $actor->id, $reason);
    }
}
