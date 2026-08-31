<?php

namespace App\Workspace;

use App\Enums\OrganizationStatus;
use App\Enums\WorkspaceType;
use App\Models\ChurchMembership;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use Illuminate\Support\Collection;

final readonly class WorkspaceRegistry
{
    public function __construct(
        private TenantContext $tenant,
        private OrganizationContext $organization,
    ) {}

    /** @return Collection<int, WorkspaceOption> */
    public function for(User $user, WorkspaceType $activeType): Collection
    {
        $churches = ChurchMembership::query()
            ->active()
            ->where('user_id', $user->id)
            ->whereHas('church', fn ($query) => $query->where('is_active', true))
            ->with('church:id,name,is_active')
            ->orderBy('church_id')
            ->get()
            ->map(fn (ChurchMembership $membership) => new WorkspaceOption(
                WorkspaceType::Church,
                $membership->church_id,
                $membership->church->name,
                $activeType === WorkspaceType::Church && $this->tenant->currentChurchId() === $membership->church_id,
            ));

        $organizations = OrganizationMembership::query()
            ->active()
            ->where('user_id', $user->id)
            ->whereHas('organization', fn ($query) => $query->where('status', OrganizationStatus::ACTIVE->value))
            ->with('organization:id,name,status')
            ->orderBy('organization_id')
            ->get()
            ->map(fn (OrganizationMembership $membership) => new WorkspaceOption(
                WorkspaceType::Organization,
                $membership->organization_id,
                $membership->organization->name,
                $activeType === WorkspaceType::Organization && $this->organization->currentOrganizationId() === $membership->organization_id,
            ));

        return $churches->concat($organizations)->values();
    }
}
