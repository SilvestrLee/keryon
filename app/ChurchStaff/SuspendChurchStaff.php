<?php

namespace App\ChurchStaff;

use App\Enums\ChurchAccessAuditEventType;
use App\Enums\MembershipStatus;
use App\Models\ChurchMembership;
use App\Support\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SuspendChurchStaff
{
    public function execute(ChurchMembership $actor, ChurchMembership $target): ChurchMembership
    {
        ChurchStaffAuthorizer::manage($actor, $target->church_id);
        if ($actor->id === $target->id) {
            throw new DomainException('Staff members cannot suspend themselves.');
        }
        $result = DB::transaction(function () use ($actor, $target): ChurchMembership {
            $locked = ChurchMembership::query()->lockForUpdate()->findOrFail($target->id);
            if ($locked->church_id !== $actor->church_id || $locked->status !== MembershipStatus::ACTIVE || $locked->is_primary) {
                throw new DomainException('This staff membership cannot be suspended.');
            }
            $locked->forceFill(['status' => MembershipStatus::SUSPENDED, 'suspended_at' => now()])->save();
            ChurchAccessAudit::record(ChurchAccessAuditEventType::STAFF_SUSPENDED, $locked->church_id, 'membership', $locked->id, $actor, ['status' => 'active'], ['status' => 'suspended']);

            return $locked->fresh('roles');
        }, 3);
        app(TenantContext::class)->forgetResolved();

        return $result;
    }
}
