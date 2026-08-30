<?php

namespace App\ChurchStaff;

use App\Enums\ChurchAccessAuditEventType;
use App\Enums\ChurchRole;
use App\Enums\MembershipStatus;
use App\Models\ChurchMembership;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UpdateChurchStaffRoles
{
    public function execute(ChurchMembership $actor, ChurchMembership $target, array $roles): ChurchMembership
    {
        ChurchStaffAuthorizer::manage($actor, $target->church_id);
        if ($actor->id === $target->id) {
            throw new DomainException('Staff members cannot change their own roles.');
        }
        $roles = RoleSet::normalize($roles);

        return DB::transaction(function () use ($actor, $target, $roles): ChurchMembership {
            $locked = ChurchMembership::query()->lockForUpdate()->findOrFail($target->id);
            if ($locked->church_id !== $actor->church_id || $locked->status !== MembershipStatus::ACTIVE) {
                throw new DomainException('Roles may only be changed for active staff in this Church.');
            }
            if ($locked->is_primary && ! in_array(ChurchRole::ADMINISTRATOR, $roles, true)) {
                throw new DomainException('The active Primary must retain Administrator.');
            }
            $previous = $locked->roleValues();
            $locked->roles()->delete();
            foreach ($roles as $role) {
                $locked->roles()->create(['role' => $role]);
            }
            ChurchAccessAudit::record(ChurchAccessAuditEventType::STAFF_ROLES_CHANGED, $locked->church_id, 'membership', $locked->id, $actor, ['roles' => $previous], ['roles' => RoleSet::values($roles)]);

            return $locked->fresh('roles');
        }, 3);
    }
}
