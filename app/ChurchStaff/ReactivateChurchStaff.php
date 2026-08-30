<?php

namespace App\ChurchStaff;

use App\Enums\ChurchAccessAuditEventType;
use App\Enums\MembershipStatus;
use App\Models\ChurchMembership;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReactivateChurchStaff
{
    public function execute(ChurchMembership $actor, ChurchMembership $target, array $roles): ChurchMembership
    {
        ChurchStaffAuthorizer::manage($actor, $target->church_id);
        if ($actor->id === $target->id) {
            throw new DomainException('Staff members cannot reactivate themselves.');
        }
        $roles = RoleSet::normalize($roles);

        return DB::transaction(function () use ($actor, $target, $roles): ChurchMembership {
            $locked = ChurchMembership::query()->lockForUpdate()->findOrFail($target->id);
            if ($locked->church_id !== $actor->church_id || $locked->status !== MembershipStatus::SUSPENDED || $locked->is_primary) {
                throw new DomainException('Only suspended non-Primary staff may be reactivated.');
            }
            $locked->roles()->delete();
            foreach ($roles as $role) {
                $locked->roles()->create(['role' => $role]);
            }
            $locked->forceFill(['status' => MembershipStatus::ACTIVE, 'activated_at' => now(), 'suspended_at' => null, 'removed_at' => null])->save();
            ChurchAccessAudit::record(ChurchAccessAuditEventType::STAFF_REACTIVATED, $locked->church_id, 'membership', $locked->id, $actor, ['status' => 'suspended'], ['status' => 'active', 'roles' => RoleSet::values($roles)]);

            return $locked->fresh('roles');
        }, 3);
    }
}
