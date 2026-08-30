<?php

namespace App\ChurchStaff;

use App\Enums\ChurchAccessAuditEventType;
use App\Enums\ChurchRole;
use App\Enums\MembershipStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use DomainException;
use Illuminate\Support\Facades\DB;

final class TransferPrimaryAdministrator
{
    public function execute(ChurchMembership $actor, ChurchMembership $target): ChurchMembership
    {
        ChurchStaffAuthorizer::manage($actor, $target->church_id);
        if (! $actor->is_primary) {
            throw new DomainException('Only the current Primary may transfer Primary Administrator.');
        }
        if ($actor->id === $target->id) {
            throw new DomainException('The target is already Primary Administrator.');
        }

        return DB::transaction(function () use ($actor, $target): ChurchMembership {
            Church::query()->lockForUpdate()->findOrFail($actor->church_id);
            $rows = ChurchMembership::query()->whereIn('id', [$actor->id, $target->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $current = $rows->get($actor->id);
            $next = $rows->get($target->id);
            if (! $current || ! $next || ! $current->is_primary || $current->status !== MembershipStatus::ACTIVE || $next->church_id !== $current->church_id || $next->status !== MembershipStatus::ACTIVE || $next->is_primary) {
                throw new DomainException('The Primary transfer is no longer valid.');
            }
            if (ChurchMembership::query()->where('church_id', $current->church_id)->active()->primary()->count() !== 1) {
                throw new DomainException('The Church Primary state requires recovery before transfer.');
            }
            if (! $next->hasRole(ChurchRole::ADMINISTRATOR)) {
                $next->roles()->create(['role' => ChurchRole::ADMINISTRATOR]);
            }
            $current->forceFill(['is_primary' => false])->save();
            $next->forceFill(['is_primary' => true])->save();
            ChurchAccessAudit::record(ChurchAccessAuditEventType::PRIMARY_TRANSFERRED, $current->church_id, 'membership', $next->id, $current, ['primary_membership_id' => $current->id], ['primary_membership_id' => $next->id]);

            return $next->fresh('roles');
        }, 3);
    }
}
