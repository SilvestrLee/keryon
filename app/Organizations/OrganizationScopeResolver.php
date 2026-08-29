<?php

namespace App\Organizations;

use App\Enums\ChurchOrganizationAssignmentStatus;
use App\Enums\OrganizationCapability;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationRoleAssignmentStatus;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationUnitStatus;
use App\Models\Church;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRoleAssignment;
use App\Models\OrganizationUnit;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class OrganizationScopeResolver
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function canViewUnit(OrganizationUnit $unit): bool
    {
        return $this->unitIsInScope($unit, OrganizationCapability::UnitsView, includeArchivedTarget: true);
    }

    public function canManageUnit(OrganizationUnit $unit): bool
    {
        return $unit->status === OrganizationUnitStatus::ACTIVE
            && $this->unitIsInScope($unit, OrganizationCapability::UnitsManage);
    }

    public function canViewChurch(Church $church): bool
    {
        return $this->churchIsInScope($church, OrganizationCapability::ChurchesView);
    }

    public function canManageChurchAssignment(Church $church): bool
    {
        return $this->churchIsInScope($church, OrganizationCapability::ChurchesManageAssignments);
    }

    public function canManageAttachmentDestination(OrganizationUnit $unit): bool
    {
        return $unit->status === OrganizationUnitStatus::ACTIVE
            && $this->unitIsInScope($unit, OrganizationCapability::ChurchesManageAssignments);
    }

    public function hasOrganizationCapability(OrganizationCapability $capability): bool
    {
        $membership = $this->trustedMembership();

        return $membership !== null && $membership->hasCapability($capability);
    }

    public function hasRootCapability(OrganizationCapability $capability): bool
    {
        $membership = $this->trustedMembership();
        $organization = $membership?->organization;

        return $membership !== null && $organization !== null
            && $this->activeAssignments($membership, $capability)
                ->where('organization_unit_id', $organization->root_unit_id)
                ->exists();
    }

    public function isAssignedScopeRoot(OrganizationUnit $unit, OrganizationCapability $capability): bool
    {
        $membership = $this->trustedMembership();

        return $membership !== null
            && $this->activeAssignments($membership, $capability)
                ->where('organization_unit_id', $unit->id)
                ->exists();
    }

    /** @return Builder<OrganizationUnit> */
    public function unitsInScope(OrganizationCapability $capability = OrganizationCapability::UnitsView, bool $includeArchived = false): Builder
    {
        $membership = $this->trustedMembership();
        $query = OrganizationUnit::query()->whereRaw('0 = 1');

        if ($membership === null) {
            return $query;
        }

        $scoped = OrganizationUnit::query()
            ->where('organization_units.organization_id', $membership->organization_id)
            ->whereExists(function ($scope) use ($membership, $capability): void {
                $scope->selectRaw('1')
                    ->from('organization_unit_paths as scope_paths')
                    ->join('organization_role_assignments as scope_roles', 'scope_roles.organization_unit_id', '=', 'scope_paths.ancestor_id')
                    ->join('organization_units as scope_units', 'scope_units.id', '=', 'scope_roles.organization_unit_id')
                    ->whereColumn('scope_paths.descendant_id', 'organization_units.id')
                    ->where('scope_paths.organization_id', $membership->organization_id)
                    ->where('scope_roles.organization_membership_id', $membership->id)
                    ->where('scope_roles.status', OrganizationRoleAssignmentStatus::ACTIVE->value)
                    ->whereIn('scope_roles.role', OrganizationRole::valuesGranting($capability))
                    ->where('scope_units.status', OrganizationUnitStatus::ACTIVE->value);
            });

        return $includeArchived
            ? $scoped
            : $scoped->where('organization_units.status', OrganizationUnitStatus::ACTIVE->value);
    }

    /** @return Builder<Church> */
    public function churchesInScope(OrganizationCapability $capability = OrganizationCapability::ChurchesView): Builder
    {
        $membership = $this->trustedMembership();
        $query = Church::query()->whereRaw('0 = 1');

        if ($membership === null) {
            return $query;
        }

        return Church::query()
            ->select('churches.*')
            ->join('church_organization_assignments as current_assignments', function ($join): void {
                $join->on('current_assignments.id', '=', 'churches.current_organization_assignment_id')
                    ->on('current_assignments.church_id', '=', 'churches.id');
            })
            ->where('current_assignments.organization_id', $membership->organization_id)
            ->where('current_assignments.status', ChurchOrganizationAssignmentStatus::ACTIVE->value)
            ->whereExists(function ($scope) use ($membership, $capability): void {
                $scope->selectRaw('1')
                    ->from('organization_unit_paths as scope_paths')
                    ->join('organization_role_assignments as scope_roles', 'scope_roles.organization_unit_id', '=', 'scope_paths.ancestor_id')
                    ->join('organization_units as scope_units', 'scope_units.id', '=', 'scope_roles.organization_unit_id')
                    ->whereColumn('scope_paths.descendant_id', 'current_assignments.organization_unit_id')
                    ->where('scope_paths.organization_id', $membership->organization_id)
                    ->where('scope_roles.organization_membership_id', $membership->id)
                    ->where('scope_roles.status', OrganizationRoleAssignmentStatus::ACTIVE->value)
                    ->whereIn('scope_roles.role', OrganizationRole::valuesGranting($capability))
                    ->where('scope_units.status', OrganizationUnitStatus::ACTIVE->value);
            });
    }

    private function unitIsInScope(OrganizationUnit $unit, OrganizationCapability $capability, bool $includeArchivedTarget = false): bool
    {
        $membership = $this->trustedMembership();
        if ($membership === null || $unit->organization_id !== $membership->organization_id) {
            return false;
        }
        if (! $includeArchivedTarget && $unit->status !== OrganizationUnitStatus::ACTIVE) {
            return false;
        }

        return DB::table('organization_unit_paths as scope_paths')
            ->join('organization_role_assignments as scope_roles', 'scope_roles.organization_unit_id', '=', 'scope_paths.ancestor_id')
            ->join('organization_units as scope_units', 'scope_units.id', '=', 'scope_roles.organization_unit_id')
            ->where('scope_paths.organization_id', $membership->organization_id)
            ->where('scope_paths.descendant_id', $unit->id)
            ->where('scope_roles.organization_membership_id', $membership->id)
            ->where('scope_roles.status', OrganizationRoleAssignmentStatus::ACTIVE->value)
            ->whereIn('scope_roles.role', OrganizationRole::valuesGranting($capability))
            ->where('scope_units.status', OrganizationUnitStatus::ACTIVE->value)
            ->exists();
    }

    private function churchIsInScope(Church $church, OrganizationCapability $capability): bool
    {
        return $this->churchesInScope($capability)->whereKey($church->id)->exists();
    }

    private function trustedMembership(): ?OrganizationMembership
    {
        $membership = $this->context->currentMembership();

        if ($membership === null
            || $membership->status !== OrganizationMembershipStatus::ACTIVE
            || $membership->organization?->status !== OrganizationStatus::ACTIVE) {
            return null;
        }

        return $membership;
    }

    /** @return HasMany<OrganizationRoleAssignment, OrganizationMembership> */
    private function activeAssignments(OrganizationMembership $membership, OrganizationCapability $capability): HasMany
    {
        return $membership->roleAssignments()
            ->where('status', OrganizationRoleAssignmentStatus::ACTIVE->value)
            ->whereIn('role', OrganizationRole::valuesGranting($capability));
    }
}
