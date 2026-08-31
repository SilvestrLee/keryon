<?php

namespace App\Support;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Support\Facades\Auth;

class OrganizationContext
{
    protected bool $resolved = false;

    protected ?OrganizationMembership $membership = null;

    protected false|null|int $resolvedForUserId = false;

    public function currentMembership(): ?OrganizationMembership
    {
        $currentUserId = Auth::check() ? Auth::id() : null;

        if (! $this->resolved || $this->resolvedForUserId !== $currentUserId) {
            $this->membership = $this->resolve();
            $this->resolved = true;
            $this->resolvedForUserId = $currentUserId;
        }

        return $this->membership;
    }

    public function currentOrganization(): ?Organization
    {
        return $this->currentMembership()?->organization;
    }

    public function currentOrganizationId(): ?int
    {
        return $this->currentMembership()?->organization_id;
    }

    public function hasContext(): bool
    {
        return $this->currentOrganizationId() !== null;
    }

    public function forgetResolved(): void
    {
        $this->resolved = false;
        $this->membership = null;
        $this->resolvedForUserId = false;
    }

    protected function resolve(): ?OrganizationMembership
    {
        if (! Auth::check()) {
            return null;
        }

        if (session('active_workspace_type') === 'church') {
            return null;
        }

        $memberships = Auth::user()->activeOrganizationMemberships()
            ->with('organization')
            ->get()
            ->filter(fn (OrganizationMembership $membership): bool => $membership->organization?->status === OrganizationStatus::ACTIVE);

        if ($memberships->count() === 1) {
            return $memberships->first();
        }

        if ($memberships->count() > 1) {
            $selectedOrganizationId = session('active_organization_id');

            if ($selectedOrganizationId !== null) {
                return $memberships->firstWhere('organization_id', (int) $selectedOrganizationId);
            }
        }

        return null;
    }
}
