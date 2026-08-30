<?php

namespace App\Onboarding;

use App\Models\ChurchMembership;
use App\Models\User;
use App\Support\TenantContext;
use DomainException;

class SelectActiveChurch
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function execute(User $user, int $churchId): ChurchMembership
    {
        $membership = $user->activeMemberships()->where('church_id', $churchId)->whereHas('church', fn ($query) => $query->where('is_active', true))->first();
        if (! $membership) {
            throw new DomainException('An active Church membership is required to select this workspace.');
        }
        session()->put('active_church_id', $churchId);
        $this->tenantContext->forgetResolved();

        return $membership;
    }
}
