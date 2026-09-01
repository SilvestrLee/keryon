<?php

namespace App\Platform;

use App\Enums\PlatformAuditEventType;
use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformAuditTargetType;
use App\Enums\PlatformCapability;
use App\Enums\PlatformMembershipStatus;
use App\Enums\PlatformRole;
use App\Models\PlatformMembership;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PlatformStaffService
{
    public function create(
        User $user,
        PlatformRole $role,
        ?PlatformMembership $actor,
        PlatformAuditReasonCategory $reason,
        ?string $note = null,
    ): PlatformMembership {
        if ($user->email_verified_at === null) {
            throw new DomainException('Platform access requires a verified User email.');
        }
        if ($actor !== null) {
            $this->authorizeActor($actor);
        }

        return DB::transaction(function () use ($user, $role, $actor, $reason, $note): PlatformMembership {
            User::query()->lockForUpdate()->findOrFail($user->id);
            if (PlatformMembership::query()->where('user_id', $user->id)->exists()) {
                throw new DomainException('This User already has a PlatformMembership.');
            }
            $membership = PlatformMembership::query()->create([
                'user_id' => $user->id,
                'role' => $role,
                'status' => PlatformMembershipStatus::ACTIVE,
                'provisioned_by_user_id' => $actor?->user_id,
                'activated_at' => now(),
            ]);
            PlatformAudit::record(
                PlatformAuditEventType::PLATFORM_ACCESS_GRANTED,
                PlatformAuditTargetType::PLATFORM_MEMBERSHIP,
                $membership->id,
                $actor,
                null,
                ['user_id' => $user->id, 'role' => $role->value, 'status' => PlatformMembershipStatus::ACTIVE->value],
                $reason,
                $note,
            );

            return $membership->fresh('user');
        });
    }

    public function suspend(
        PlatformMembership $target,
        PlatformMembership $actor,
        PlatformAuditReasonCategory $reason,
        ?string $note = null,
    ): PlatformMembership {
        return $this->endAccess($target, $actor, PlatformMembershipStatus::SUSPENDED, $reason, $note);
    }

    public function remove(
        PlatformMembership $target,
        PlatformMembership $actor,
        PlatformAuditReasonCategory $reason,
        ?string $note = null,
    ): PlatformMembership {
        return $this->endAccess($target, $actor, PlatformMembershipStatus::REMOVED, $reason, $note);
    }

    private function endAccess(
        PlatformMembership $target,
        PlatformMembership $actor,
        PlatformMembershipStatus $status,
        PlatformAuditReasonCategory $reason,
        ?string $note,
    ): PlatformMembership {
        $this->authorizeActor($actor);
        if ($target->id === $actor->id) {
            throw new DomainException('Platform Administrators cannot suspend or remove their own active access.');
        }

        return DB::transaction(function () use ($target, $actor, $status, $reason, $note): PlatformMembership {
            $lockedActor = PlatformMembership::query()->lockForUpdate()->findOrFail($actor->id);
            $this->authorizeActor($lockedActor);
            $locked = PlatformMembership::query()->lockForUpdate()->findOrFail($target->id);
            if ($locked->status !== PlatformMembershipStatus::ACTIVE) {
                throw new DomainException('Only active platform access may be suspended or removed.');
            }
            if ($locked->role === PlatformRole::ADMINISTRATOR) {
                $otherAdministrators = PlatformMembership::query()
                    ->active()
                    ->where('role', PlatformRole::ADMINISTRATOR->value)
                    ->where('id', '!=', $locked->id)
                    ->lockForUpdate()
                    ->count();
                if ($otherAdministrators === 0) {
                    throw new DomainException('The final active Platform Administrator cannot be suspended or removed.');
                }
            }
            $previous = ['role' => $locked->role->value, 'status' => $locked->status->value];
            $locked->forceFill([
                'status' => $status,
                'suspended_at' => $status === PlatformMembershipStatus::SUSPENDED ? now() : null,
                'removed_at' => $status === PlatformMembershipStatus::REMOVED ? now() : null,
            ])->save();
            PlatformAudit::record(
                $status === PlatformMembershipStatus::SUSPENDED
                    ? PlatformAuditEventType::PLATFORM_ACCESS_SUSPENDED
                    : PlatformAuditEventType::PLATFORM_ACCESS_REMOVED,
                PlatformAuditTargetType::PLATFORM_MEMBERSHIP,
                $locked->id,
                $lockedActor,
                $previous,
                ['role' => $locked->role->value, 'status' => $status->value],
                $reason,
                $note,
            );

            return $locked->fresh('user');
        });
    }

    private function authorizeActor(PlatformMembership $actor): void
    {
        $fresh = PlatformMembership::query()->find($actor->id);
        if ($fresh === null || ! $fresh->hasCapability(PlatformCapability::PlatformStaffManage)) {
            throw new DomainException('Active Platform Staff management authority is required.');
        }
    }
}
