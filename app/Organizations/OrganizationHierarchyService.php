<?php

namespace App\Organizations;

use App\Enums\ChurchOrganizationAssignmentStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationAuditSubjectType;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationUnitStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ChurchOrganizationAssignment;
use App\Models\Organization;
use App\Models\OrganizationAuditEvent;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitPath;
use App\Models\OrganizationUnitType;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationHierarchyService
{
    public function createOrganization(string $name, string $slug, ?int $actorUserId = null): Organization
    {
        return DB::transaction(function () use ($name, $slug, $actorUserId): Organization {
            $organization = Organization::query()->create([
                'name' => $name,
                'slug' => Str::slug($slug),
                'status' => OrganizationStatus::ACTIVE,
            ]);
            $rootType = OrganizationUnitType::query()->create([
                'organization_id' => $organization->id,
                'code' => '__root__',
                'label' => 'Organization',
                'sort_order' => 0,
                'is_active' => false,
            ]);
            $root = OrganizationUnit::query()->create([
                'organization_id' => $organization->id,
                'organization_unit_type_id' => $rootType->id,
                'parent_id' => null,
                'code' => '__root__',
                'name' => $organization->name,
                'status' => OrganizationUnitStatus::ACTIVE,
            ]);
            OrganizationUnitPath::query()->create([
                'organization_id' => $organization->id,
                'ancestor_id' => $root->id,
                'descendant_id' => $root->id,
                'depth' => 0,
            ]);
            $organization->forceFill(['root_unit_id' => $root->id])->save();
            $this->audit($organization, OrganizationAuditEventType::ORGANIZATION_CREATED, OrganizationAuditSubjectType::ORGANIZATION, $organization->id, $actorUserId, null, $root, null, [
                'status' => $organization->status->value,
                'root_unit_id' => $root->id,
            ]);

            return $organization->fresh('rootUnit');
        });
    }

    /** @param array{name:string,code:string,status?:OrganizationUnitStatus|string} $attributes */
    public function createUnit(Organization $organization, OrganizationUnitType $type, OrganizationUnit $parent, array $attributes, ?int $actorUserId = null): OrganizationUnit
    {
        return DB::transaction(function () use ($organization, $type, $parent, $attributes, $actorUserId): OrganizationUnit {
            $lockedOrganization = Organization::query()->lockForUpdate()->findOrFail($organization->id);
            $lockedParent = OrganizationUnit::query()->lockForUpdate()->findOrFail($parent->id);
            $lockedType = OrganizationUnitType::query()->lockForUpdate()->findOrFail($type->id);
            $this->assertActiveOrganization($lockedOrganization);

            if ($lockedParent->organization_id !== $lockedOrganization->id || $lockedType->organization_id !== $lockedOrganization->id) {
                throw new DomainException('Unit parent and type must belong to the Organization.');
            }
            if ($lockedParent->status !== OrganizationUnitStatus::ACTIVE || ! $lockedType->is_active) {
                throw new DomainException('Archived parents and inactive Unit types cannot receive Units.');
            }

            $unit = OrganizationUnit::query()->create([
                'organization_id' => $lockedOrganization->id,
                'organization_unit_type_id' => $lockedType->id,
                'parent_id' => $lockedParent->id,
                'code' => $attributes['code'],
                'name' => $attributes['name'],
                'status' => $attributes['status'] ?? OrganizationUnitStatus::ACTIVE,
            ]);
            OrganizationUnitPath::query()->create([
                'organization_id' => $lockedOrganization->id,
                'ancestor_id' => $unit->id,
                'descendant_id' => $unit->id,
                'depth' => 0,
            ]);
            $ancestors = OrganizationUnitPath::query()
                ->where('organization_id', $lockedOrganization->id)
                ->where('descendant_id', $lockedParent->id)
                ->orderBy('ancestor_id')
                ->lockForUpdate()
                ->get();
            foreach ($ancestors as $path) {
                OrganizationUnitPath::query()->create([
                    'organization_id' => $lockedOrganization->id,
                    'ancestor_id' => $path->ancestor_id,
                    'descendant_id' => $unit->id,
                    'depth' => $path->depth + 1,
                ]);
            }
            $this->audit($lockedOrganization, OrganizationAuditEventType::UNIT_CREATED, OrganizationAuditSubjectType::UNIT, $unit->id, $actorUserId, null, $unit, null, [
                'parent_id' => $lockedParent->id,
                'code' => $unit->code,
            ]);

            return $unit->fresh();
        });
    }

    public function moveUnit(OrganizationUnit $unit, OrganizationUnit $newParent, ?int $actorUserId = null): OrganizationUnit
    {
        return DB::transaction(function () use ($unit, $newParent, $actorUserId): OrganizationUnit {
            [$firstId, $secondId] = collect([$unit->id, $newParent->id])->sort()->values()->all();
            $locked = OrganizationUnit::query()->whereIn('id', [$firstId, $secondId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $moving = $locked->get($unit->id) ?? throw new DomainException('The Unit no longer exists.');
            $parent = $locked->get($newParent->id) ?? throw new DomainException('The parent Unit no longer exists.');
            $organization = Organization::query()->lockForUpdate()->findOrFail($moving->organization_id);

            if ($moving->organization_id !== $parent->organization_id) {
                throw new DomainException('Units cannot move across Organizations.');
            }
            if ($organization->root_unit_id === $moving->id) {
                throw new DomainException('The Organization root Unit cannot be moved.');
            }
            if ($moving->id === $parent->id || OrganizationUnitPath::query()->where('ancestor_id', $moving->id)->where('descendant_id', $parent->id)->exists()) {
                throw new DomainException('A Unit cannot be moved beneath itself or its descendant.');
            }
            if ($parent->status !== OrganizationUnitStatus::ACTIVE) {
                throw new DomainException('An archived Unit cannot receive children.');
            }

            $subtreePaths = OrganizationUnitPath::query()->where('organization_id', $organization->id)->where('ancestor_id', $moving->id)->orderBy('descendant_id')->lockForUpdate()->get();
            $subtreeIds = $subtreePaths->pluck('descendant_id')->all();
            $oldParentId = $moving->parent_id;

            OrganizationUnitPath::query()
                ->where('organization_id', $organization->id)
                ->whereIn('descendant_id', $subtreeIds)
                ->whereNotIn('ancestor_id', $subtreeIds)
                ->delete();

            $newAncestors = OrganizationUnitPath::query()->where('organization_id', $organization->id)->where('descendant_id', $parent->id)->orderBy('ancestor_id')->lockForUpdate()->get();
            $rows = [];
            foreach ($newAncestors as $ancestorPath) {
                foreach ($subtreePaths as $subtreePath) {
                    $rows[] = [
                        'organization_id' => $organization->id,
                        'ancestor_id' => $ancestorPath->ancestor_id,
                        'descendant_id' => $subtreePath->descendant_id,
                        'depth' => $ancestorPath->depth + 1 + $subtreePath->depth,
                    ];
                }
            }
            OrganizationUnitPath::query()->insert($rows);
            $moving->forceFill(['parent_id' => $parent->id])->save();
            $this->audit($organization, OrganizationAuditEventType::UNIT_MOVED, OrganizationAuditSubjectType::UNIT, $moving->id, $actorUserId, null, $moving, ['parent_id' => $oldParentId], ['parent_id' => $parent->id]);

            return $moving->fresh();
        });
    }

    public function archiveUnit(OrganizationUnit $unit, ?int $actorUserId = null): OrganizationUnit
    {
        return DB::transaction(function () use ($unit, $actorUserId): OrganizationUnit {
            $locked = OrganizationUnit::query()->lockForUpdate()->findOrFail($unit->id);
            $organization = Organization::query()->lockForUpdate()->findOrFail($locked->organization_id);
            if ($organization->root_unit_id === $locked->id) {
                throw new DomainException('The Organization root Unit cannot be archived.');
            }
            if (OrganizationUnit::query()->where('parent_id', $locked->id)->where('status', OrganizationUnitStatus::ACTIVE->value)->exists()) {
                throw new DomainException('A Unit with active child Units cannot be archived.');
            }
            if (Church::query()->whereHas('currentOrganizationAssignment', fn ($query) => $query->where('organization_unit_id', $locked->id))->exists()) {
                throw new DomainException('A Unit with active Church assignments cannot be archived.');
            }
            $locked->forceFill(['status' => OrganizationUnitStatus::ARCHIVED])->save();
            $this->audit($organization, OrganizationAuditEventType::UNIT_ARCHIVED, OrganizationAuditSubjectType::UNIT, $locked->id, $actorUserId, null, $locked, ['status' => OrganizationUnitStatus::ACTIVE->value], ['status' => OrganizationUnitStatus::ARCHIVED->value]);

            return $locked->fresh();
        });
    }

    public function attachChurch(Organization $organization, OrganizationUnit $unit, Church $church, ?int $requestedBy = null): ChurchOrganizationAssignment
    {
        return DB::transaction(function () use ($organization, $unit, $church, $requestedBy): ChurchOrganizationAssignment {
            $lockedOrganization = Organization::query()->lockForUpdate()->findOrFail($organization->id);
            $lockedUnit = OrganizationUnit::query()->lockForUpdate()->findOrFail($unit->id);
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->id);
            $this->assertActiveOrganization($lockedOrganization);
            if ($lockedUnit->organization_id !== $lockedOrganization->id || $lockedUnit->status !== OrganizationUnitStatus::ACTIVE) {
                throw new DomainException('Churches can only be requested into an active Unit of the Organization.');
            }
            if ($lockedChurch->current_organization_assignment_id !== null) {
                throw new DomainException('The Church already has a current governing Organization assignment.');
            }
            $existing = ChurchOrganizationAssignment::query()
                ->where('church_id', $lockedChurch->id)
                ->where('organization_id', $lockedOrganization->id)
                ->where('organization_unit_id', $lockedUnit->id)
                ->where('status', ChurchOrganizationAssignmentStatus::PENDING->value)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                return $existing;
            }
            $assignment = ChurchOrganizationAssignment::query()->create([
                'church_id' => $lockedChurch->id,
                'organization_id' => $lockedOrganization->id,
                'organization_unit_id' => $lockedUnit->id,
                'status' => ChurchOrganizationAssignmentStatus::PENDING,
                'requested_by' => $requestedBy,
                'requested_at' => now(),
            ]);
            $this->audit($lockedOrganization, OrganizationAuditEventType::ATTACHMENT_REQUESTED, OrganizationAuditSubjectType::CHURCH_ASSIGNMENT, $assignment->id, $requestedBy, $lockedChurch, $lockedUnit, null, ['status' => 'pending']);

            return $assignment->fresh();
        });
    }

    public function acceptAttachment(ChurchOrganizationAssignment $assignment, User $actor): ChurchOrganizationAssignment
    {
        return DB::transaction(function () use ($assignment, $actor): ChurchOrganizationAssignment {
            $locked = ChurchOrganizationAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $church = Church::query()->lockForUpdate()->findOrFail($locked->church_id);
            $this->assertPending($locked);
            $this->assertActivePrimary($church, $actor);
            if ($church->current_organization_assignment_id !== null) {
                throw new DomainException('The Church already has a current governing Organization assignment.');
            }
            $organization = Organization::query()->lockForUpdate()->findOrFail($locked->organization_id);
            $unit = OrganizationUnit::query()->lockForUpdate()->findOrFail($locked->organization_unit_id);
            $this->assertActiveOrganization($organization);
            if ($unit->status !== OrganizationUnitStatus::ACTIVE) {
                throw new DomainException('An archived Unit cannot receive a Church assignment.');
            }
            $locked->forceFill([
                'status' => ChurchOrganizationAssignmentStatus::ACTIVE,
                'accepted_by' => $actor->id,
                'effective_at' => now(),
            ])->save();
            $church->forceFill(['current_organization_assignment_id' => $locked->id])->save();
            $this->audit($organization, OrganizationAuditEventType::ATTACHMENT_ACCEPTED, OrganizationAuditSubjectType::CHURCH_ASSIGNMENT, $locked->id, $actor->id, $church, $unit, ['status' => 'pending'], ['status' => 'active']);

            return $locked->fresh();
        });
    }

    public function rejectAttachment(ChurchOrganizationAssignment $assignment, User $actor, ?string $reason = null): ChurchOrganizationAssignment
    {
        return DB::transaction(function () use ($assignment, $actor, $reason): ChurchOrganizationAssignment {
            $locked = ChurchOrganizationAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $church = Church::query()->lockForUpdate()->findOrFail($locked->church_id);
            $this->assertPending($locked);
            $this->assertActivePrimary($church, $actor);
            $locked->forceFill([
                'status' => ChurchOrganizationAssignmentStatus::REJECTED,
                'ended_at' => now(),
                'reason' => $this->boundedReason($reason),
            ])->save();
            $this->audit($locked->organization, OrganizationAuditEventType::ATTACHMENT_REJECTED, OrganizationAuditSubjectType::CHURCH_ASSIGNMENT, $locked->id, $actor->id, $church, $locked->unit, ['status' => 'pending'], ['status' => 'rejected']);

            return $locked->fresh();
        });
    }

    public function moveChurch(Church $church, OrganizationUnit $newUnit, ?int $actorUserId = null): ChurchOrganizationAssignment
    {
        return DB::transaction(function () use ($church, $newUnit, $actorUserId): ChurchOrganizationAssignment {
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->id);
            $currentId = $lockedChurch->current_organization_assignment_id ?? throw new DomainException('The Church has no current Organization assignment.');
            $current = ChurchOrganizationAssignment::query()->lockForUpdate()->findOrFail($currentId);
            $unit = OrganizationUnit::query()->lockForUpdate()->findOrFail($newUnit->id);
            if ($current->organization_id !== $unit->organization_id) {
                throw new DomainException('A direct Church move cannot cross Organizations.');
            }
            if ($unit->status !== OrganizationUnitStatus::ACTIVE) {
                throw new DomainException('An archived Unit cannot receive a Church assignment.');
            }
            $current->forceFill(['status' => ChurchOrganizationAssignmentStatus::ENDED, 'ended_at' => now(), 'reason' => 'moved'])->save();
            $replacement = ChurchOrganizationAssignment::query()->create([
                'church_id' => $lockedChurch->id,
                'organization_id' => $current->organization_id,
                'organization_unit_id' => $unit->id,
                'status' => ChurchOrganizationAssignmentStatus::ACTIVE,
                'requested_by' => $actorUserId,
                'accepted_by' => $actorUserId,
                'requested_at' => now(),
                'effective_at' => now(),
                'reason' => 'unit_move',
            ]);
            $lockedChurch->forceFill(['current_organization_assignment_id' => $replacement->id])->save();
            $this->audit($current->organization, OrganizationAuditEventType::CHURCH_MOVED, OrganizationAuditSubjectType::CHURCH_ASSIGNMENT, $replacement->id, $actorUserId, $lockedChurch, $unit, ['assignment_id' => $current->id, 'unit_id' => $current->organization_unit_id], ['assignment_id' => $replacement->id, 'unit_id' => $unit->id]);

            return $replacement->fresh();
        });
    }

    public function detachChurch(Church $church, ?int $actorUserId = null, ?string $reason = null): ChurchOrganizationAssignment
    {
        return DB::transaction(function () use ($church, $actorUserId, $reason): ChurchOrganizationAssignment {
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->id);
            $currentId = $lockedChurch->current_organization_assignment_id ?? throw new DomainException('The Church has no current Organization assignment.');
            $current = ChurchOrganizationAssignment::query()->lockForUpdate()->findOrFail($currentId);
            $current->forceFill([
                'status' => ChurchOrganizationAssignmentStatus::ENDED,
                'ended_at' => now(),
                'reason' => $this->boundedReason($reason) ?? 'detached',
            ])->save();
            $lockedChurch->forceFill(['current_organization_assignment_id' => null])->save();
            $this->audit($current->organization, OrganizationAuditEventType::CHURCH_DETACHED, OrganizationAuditSubjectType::CHURCH_ASSIGNMENT, $current->id, $actorUserId, $lockedChurch, $current->unit, ['status' => 'active'], ['status' => 'ended']);

            return $current->fresh();
        });
    }

    private function assertActiveOrganization(Organization $organization): void
    {
        if ($organization->status !== OrganizationStatus::ACTIVE) {
            throw new DomainException('The Organization must be active.');
        }
    }

    private function assertPending(ChurchOrganizationAssignment $assignment): void
    {
        if ($assignment->status !== ChurchOrganizationAssignmentStatus::PENDING) {
            throw new DomainException('Only a pending Church attachment may transition through acceptance or rejection.');
        }
    }

    private function assertActivePrimary(Church $church, User $actor): ChurchMembership
    {
        return ChurchMembership::query()
            ->where('church_id', $church->id)
            ->where('user_id', $actor->id)
            ->where('status', MembershipStatus::ACTIVE->value)
            ->where('is_primary', true)
            ->first() ?? throw new DomainException('Church attachment acceptance requires the active Primary ChurchMembership.');
    }

    private function boundedReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new DomainException('Assignment reasons must contain between 1 and 500 characters.');
        }

        return $reason;
    }

    /** @param array<string,mixed>|null $previous @param array<string,mixed>|null $new */
    private function audit(Organization $organization, OrganizationAuditEventType $event, OrganizationAuditSubjectType $subject, int $subjectId, ?int $actorUserId, ?Church $church, ?OrganizationUnit $unit, ?array $previous, ?array $new): void
    {
        OrganizationAuditEvent::query()->create([
            'organization_id' => $organization->id,
            'event_type' => $event,
            'subject_type' => $subject,
            'subject_id' => $subjectId,
            'church_id' => $church?->id,
            'organization_unit_id' => $unit?->id,
            'actor_user_id' => $actorUserId,
            'previous_state' => $previous,
            'new_state' => $new,
            'occurred_at' => now(),
        ]);
    }
}
