<?php

namespace App\ChurchStaff;

use App\Enums\Capability;
use App\Enums\MembershipStatus;
use App\Models\ChurchMembership;
use App\Support\TenantContext;
use DomainException;

final class ChurchStaffAuthorizer
{
    public static function manage(ChurchMembership $actor, int $churchId): void
    {
        $context = app(TenantContext::class)->currentMembership();
        if ($actor->status !== MembershipStatus::ACTIVE || $actor->church_id !== $churchId
            || $context?->id !== $actor->id || ! $actor->hasCapability(Capability::StaffManage)) {
            throw new DomainException('An active StaffManage membership in this Church is required.');
        }
    }
}
