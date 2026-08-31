<?php

namespace App\Filament\Organization\Pages;

use App\Enums\OrganizationStatus;
use App\Filament\Organization\Concerns\InteractsWithOrganizationWorkspace;
use App\Models\OrganizationMembership;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class SelectOrganization extends Page
{
    use InteractsWithOrganizationWorkspace;

    protected string $view = 'filament.organization.pages.select-organization';

    protected static ?string $title = 'Choose Organization';

    protected static ?string $slug = 'select-organization';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->activeOrganizationMemberships()
            ->whereHas('organization', fn ($query) => $query->where('status', OrganizationStatus::ACTIVE->value))
            ->exists();
    }

    /** @return Collection<int, OrganizationMembership> */
    public function memberships(): Collection
    {
        return auth()->user()->activeOrganizationMemberships()
            ->with('organization')
            ->whereHas('organization', fn ($query) => $query->where('status', OrganizationStatus::ACTIVE->value))
            ->orderBy('organization_id')
            ->get();
    }

    public function selectOrganization(int $organizationId): void
    {
        $membership = $this->memberships()->firstWhere('organization_id', $organizationId);
        abort_unless($membership !== null, 403);

        session()->forget('active_church_id');
        session(['active_organization_id' => $membership->organization_id, 'active_workspace_type' => 'organization']);
        app(TenantContext::class)->forgetResolved();
        app(OrganizationContext::class)->forgetResolved();

        $this->redirect(OrganizationOverview::getUrl(panel: 'organization'), navigate: true);
    }
}
