<?php

namespace App\Organizations;

use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationAuditSubjectType;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationRoleAssignmentStatus;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationUnitStatus;
use App\Models\Organization;
use App\Models\OrganizationAuditEvent;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRoleAssignment;
use App\Models\OrganizationUnit;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class OrganizationIdentityService
{
    public function bootstrapAdministrator(Organization $organization, User $user, ?int $actorUserId = null): OrganizationMembership
    {
        return DB::transaction(function () use ($organization, $user, $actorUserId): OrganizationMembership {
            $lockedOrganization = Organization::query()->lockForUpdate()->findOrFail($organization->id);
            $this->assertActiveOrganization($lockedOrganization);
            if ($lockedOrganization->memberships()->exists()) {
                throw new DomainException('The first Organization Administrator has already been established.');
            }

            $membership = OrganizationMembership::query()->create([
                'organization_id' => $lockedOrganization->id,
                'user_id' => $user->id,
                'status' => OrganizationMembershipStatus::ACTIVE,
                'joined_at' => now(),
            ]);
            $role = $this->persistRole($membership, $lockedOrganization->rootUnit, OrganizationRole::ORGANIZATION_ADMINISTRATOR);

            $this->audit($lockedOrganization, OrganizationAuditEventType::MEMBERSHIP_CREATED, OrganizationAuditSubjectType::MEMBERSHIP, $membership->id, $actorUserId, ['status' => 'active']);
            $this->audit($lockedOrganization, OrganizationAuditEventType::MEMBERSHIP_ACTIVATED, OrganizationAuditSubjectType::MEMBERSHIP, $membership->id, $actorUserId, ['status' => 'active']);
            $this->audit($lockedOrganization, OrganizationAuditEventType::ROLE_ASSIGNED, OrganizationAuditSubjectType::ROLE_ASSIGNMENT, $role->id, $actorUserId, ['role' => $role->role->value, 'unit_id' => $role->organization_unit_id]);

            return $membership->fresh('roleAssignments');
        });
    }

    public function invite(Organization $organization, User $user, ?int $actorUserId = null): OrganizationMembership
    {
        return DB::transaction(function () use ($organization, $user, $actorUserId): OrganizationMembership {
            $lockedOrganization = Organization::query()->lockForUpdate()->findOrFail($organization->id);
            $this->assertActiveOrganization($lockedOrganization);
            $membership = OrganizationMembership::query()->firstOrCreate(
                ['organization_id' => $lockedOrganization->id, 'user_id' => $user->id],
                ['status' => OrganizationMembershipStatus::INVITED, 'invited_at' => now()],
            );
            if (! $membership->wasRecentlyCreated && $membership->status !== OrganizationMembershipStatus::INVITED) {
                throw new DomainException('The User already has an Organization membership lifecycle record.');
            }
            if ($membership->wasRecentlyCreated) {
                $this->audit($lockedOrganization, OrganizationAuditEventType::MEMBERSHIP_INVITED, OrganizationAuditSubjectType::MEMBERSHIP, $membership->id, $actorUserId, ['status' => 'invited']);
            }

            return $membership->fresh();
        });
    }

    public function activate(OrganizationMembership $membership, ?int $actorUserId = null): OrganizationMembership
    {
        return DB::transaction(function () use ($membership, $actorUserId): OrganizationMembership {
            $locked = OrganizationMembership::query()->lockForUpdate()->findOrFail($membership->id);
            $organization = Organization::query()->lockForUpdate()->findOrFail($locked->organization_id);
            $this->assertActiveOrganization($organization);
            if (! in_array($locked->status, [OrganizationMembershipStatus::INVITED, OrganizationMembershipStatus::SUSPENDED], true)) {
                throw new DomainException('Only invited or suspended Organization memberships may be activated.');
            }
            $previous = $locked->status->value;
            $locked->forceFill([
                'status' => OrganizationMembershipStatus::ACTIVE,
                'joined_at' => $locked->joined_at ?? now(),
                'suspended_at' => null,
                'removed_at' => null,
            ])->save();
            $this->audit($organization, OrganizationAuditEventType::MEMBERSHIP_ACTIVATED, OrganizationAuditSubjectType::MEMBERSHIP, $locked->id, $actorUserId, ['status' => 'active'], ['status' => $previous]);

            return $locked->fresh();
        });
    }

    public function suspend(OrganizationMembership $membership, ?int $actorUserId = null): OrganizationMembership
    {
        return $this->endMembership($membership, OrganizationMembershipStatus::SUSPENDED, $actorUserId);
    }

    public function remove(OrganizationMembership $membership, ?int $actorUserId = null): OrganizationMembership
    {
        return $this->endMembership($membership, OrganizationMembershipStatus::REMOVED, $actorUserId);
    }

    public function assignRole(OrganizationMembership $membership, OrganizationRole $role, OrganizationUnit $unit, ?int $actorUserId = null): OrganizationRoleAssignment
    {
        return DB::transaction(function () use ($membership, $role, $unit, $actorUserId): OrganizationRoleAssignment {
            $lockedMembership = OrganizationMembership::query()->lockForUpdate()->findOrFail($membership->id);
            $lockedUnit = OrganizationUnit::query()->lockForUpdate()->findOrFail($unit->id);
            if ($lockedMembership->status !== OrganizationMembershipStatus::ACTIVE) {
                throw new DomainException('Roles may only be assigned to active Organization memberships.');
            }
            if ($lockedMembership->organization_id !== $lockedUnit->organization_id || $lockedUnit->status !== OrganizationUnitStatus::ACTIVE) {
                throw new DomainException('Role scope must be an active Unit in the membership Organization.');
            }
            $organization = Organization::query()->lockForUpdate()->findOrFail($lockedMembership->organization_id);
            $this->assertActiveOrganization($organization);
            if ($role === OrganizationRole::ORGANIZATION_ADMINISTRATOR && $lockedUnit->id !== $organization->root_unit_id) {
                throw new DomainException('Organization Administrator must be scoped at the explicit Organization root.');
            }
            $assignment = $this->persistRole($lockedMembership, $lockedUnit, $role);
            $this->audit($organization, OrganizationAuditEventType::ROLE_ASSIGNED, OrganizationAuditSubjectType::ROLE_ASSIGNMENT, $assignment->id, $actorUserId, ['role' => $role->value, 'unit_id' => $lockedUnit->id]);

            return $assignment->fresh();
        });
    }

    public function suspendRole(OrganizationRoleAssignment $assignment, ?int $actorUserId = null): OrganizationRoleAssignment
    {
        return $this->endRole($assignment, OrganizationRoleAssignmentStatus::SUSPENDED, $actorUserId);
    }

    public function removeRole(OrganizationRoleAssignment $assignment, ?int $actorUserId = null): OrganizationRoleAssignment
    {
        return $this->endRole($assignment, OrganizationRoleAssignmentStatus::REMOVED, $actorUserId);
    }

    public function changeRoleScope(OrganizationRoleAssignment $assignment, OrganizationUnit $newUnit, ?int $actorUserId = null): OrganizationRoleAssignment
    {
        return DB::transaction(function () use ($assignment, $newUnit, $actorUserId): OrganizationRoleAssignment {
            $locked = OrganizationRoleAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $membership = OrganizationMembership::query()->lockForUpdate()->findOrFail($locked->organization_membership_id);
            $unit = OrganizationUnit::query()->lockForUpdate()->findOrFail($newUnit->id);
            $organization = Organization::query()->lockForUpdate()->findOrFail($membership->organization_id);
            if ($locked->status !== OrganizationRoleAssignmentStatus::ACTIVE
                || $membership->status !== OrganizationMembershipStatus::ACTIVE
                || $unit->organization_id !== $membership->organization_id
                || $unit->status !== OrganizationUnitStatus::ACTIVE) {
                throw new DomainException('Only an active role may move to an active scope in its Organization.');
            }
            if ($locked->role === OrganizationRole::ORGANIZATION_ADMINISTRATOR && $unit->id !== $organization->root_unit_id) {
                throw new DomainException('Organization Administrator must remain scoped at the root.');
            }
            $oldUnitId = $locked->organization_unit_id;
            $locked->forceFill(['organization_unit_id' => $unit->id])->save();
            $this->audit($organization, OrganizationAuditEventType::ROLE_SCOPE_CHANGED, OrganizationAuditSubjectType::ROLE_ASSIGNMENT, $locked->id, $actorUserId, ['role' => $locked->role->value, 'unit_id' => $unit->id], ['unit_id' => $oldUnitId]);

            return $locked->fresh();
        });
    }

    private function endMembership(OrganizationMembership $membership, OrganizationMembershipStatus $status, ?int $actorUserId): OrganizationMembership
    {
        return DB::transaction(function () use ($membership, $status, $actorUserId): OrganizationMembership {
            $locked = OrganizationMembership::query()->lockForUpdate()->findOrFail($membership->id);
            if ($locked->status !== OrganizationMembershipStatus::ACTIVE) {
                throw new DomainException('Only an active Organization membership may be suspended or removed.');
            }
            $organization = Organization::query()->lockForUpdate()->findOrFail($locked->organization_id);
            $this->guardLastAdministrator($locked, $organization);
            $timeColumn = $status === OrganizationMembershipStatus::SUSPENDED ? 'suspended_at' : 'removed_at';
            $locked->forceFill(['status' => $status, $timeColumn => now()])->save();
            $event = $status === OrganizationMembershipStatus::SUSPENDED
                ? OrganizationAuditEventType::MEMBERSHIP_SUSPENDED
                : OrganizationAuditEventType::MEMBERSHIP_REMOVED;
            $this->audit($organization, $event, OrganizationAuditSubjectType::MEMBERSHIP, $locked->id, $actorUserId, ['status' => $status->value], ['status' => 'active']);

            return $locked->fresh();
        });
    }

    private function endRole(OrganizationRoleAssignment $assignment, OrganizationRoleAssignmentStatus $status, ?int $actorUserId): OrganizationRoleAssignment
    {
        return DB::transaction(function () use ($assignment, $status, $actorUserId): OrganizationRoleAssignment {
            $locked = OrganizationRoleAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            if ($locked->status !== OrganizationRoleAssignmentStatus::ACTIVE) {
                throw new DomainException('Only an active Organization role assignment may be suspended or removed.');
            }
            $membership = OrganizationMembership::query()->lockForUpdate()->findOrFail($locked->organization_membership_id);
            $organization = Organization::query()->lockForUpdate()->findOrFail($membership->organization_id);
            if ($locked->role === OrganizationRole::ORGANIZATION_ADMINISTRATOR && $locked->organization_unit_id === $organization->root_unit_id) {
                $this->guardLastAdministrator($membership, $organization);
            }
            $timeColumn = $status === OrganizationRoleAssignmentStatus::SUSPENDED ? 'suspended_at' : 'removed_at';
            $locked->forceFill(['status' => $status, $timeColumn => now()])->save();
            $event = $status === OrganizationRoleAssignmentStatus::SUSPENDED
                ? OrganizationAuditEventType::ROLE_SUSPENDED
                : OrganizationAuditEventType::ROLE_REMOVED;
            $this->audit($organization, $event, OrganizationAuditSubjectType::ROLE_ASSIGNMENT, $locked->id, $actorUserId, ['status' => $status->value], ['status' => 'active']);

            return $locked->fresh();
        });
    }

    private function persistRole(OrganizationMembership $membership, OrganizationUnit $unit, OrganizationRole $role): OrganizationRoleAssignment
    {
        $assignment = OrganizationRoleAssignment::query()->firstOrNew([
            'organization_membership_id' => $membership->id,
            'organization_unit_id' => $unit->id,
            'role' => $role->value,
        ]);
        $assignment->forceFill([
            'status' => OrganizationRoleAssignmentStatus::ACTIVE,
            'assigned_at' => now(),
            'suspended_at' => null,
            'removed_at' => null,
        ])->save();

        return $assignment;
    }

    private function guardLastAdministrator(OrganizationMembership $membership, Organization $organization): void
    {
        $membershipIsRootAdmin = $membership->roleAssignments()
            ->where('role', OrganizationRole::ORGANIZATION_ADMINISTRATOR->value)
            ->where('organization_unit_id', $organization->root_unit_id)
            ->where('status', OrganizationRoleAssignmentStatus::ACTIVE->value)
            ->exists();
        if (! $membershipIsRootAdmin) {
            return;
        }

        $otherAdministratorExists = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->whereKeyNot($membership->id)
            ->where('status', OrganizationMembershipStatus::ACTIVE->value)
            ->whereHas('roleAssignments', fn ($query) => $query
                ->where('role', OrganizationRole::ORGANIZATION_ADMINISTRATOR->value)
                ->where('organization_unit_id', $organization->root_unit_id)
                ->where('status', OrganizationRoleAssignmentStatus::ACTIVE->value))
            ->exists();

        if (! $otherAdministratorExists) {
            throw new DomainException('An active Organization must retain at least one active root Administrator.');
        }
    }

    private function assertActiveOrganization(Organization $organization): void
    {
        if ($organization->status !== OrganizationStatus::ACTIVE) {
            throw new DomainException('Organization identity may only be changed for an active Organization.');
        }
    }

    /** @param array<string, mixed> $new @param array<string, mixed>|null $previous */
    private function audit(Organization $organization, OrganizationAuditEventType $event, OrganizationAuditSubjectType $subject, int $subjectId, ?int $actorUserId, array $new, ?array $previous = null): void
    {
        OrganizationAuditEvent::query()->create([
            'organization_id' => $organization->id,
            'event_type' => $event,
            'subject_type' => $subject,
            'subject_id' => $subjectId,
            'actor_user_id' => $actorUserId,
            'previous_state' => $previous,
            'new_state' => $new,
            'occurred_at' => now(),
        ]);
    }
}
