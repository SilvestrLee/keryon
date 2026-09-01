<?php

namespace App\Domain;

use App\Enums\Capability;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\ChurchMembership;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class DomainMutationAuthorizer
{
    public function __construct(private TenantContext $tenant) {}

    public function authorize(Church|ChurchDomain $subject): ChurchMembership
    {
        $membership = $this->tenant->currentMembership();
        $churchId = $subject instanceof Church ? $subject->getKey() : $subject->church_id;

        if ($membership === null
            || $membership->church_id !== $churchId
            || ! $membership->is_primary
            || ! $membership->hasCapability(Capability::WebsiteDomainManage)) {
            throw new AuthorizationException('Only the active Primary Administrator may manage Church domains.');
        }

        return $membership;
    }
}
