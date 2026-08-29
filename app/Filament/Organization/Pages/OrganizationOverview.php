<?php

namespace App\Filament\Organization\Pages;

use App\Enums\OrganizationCapability;
use App\Filament\Organization\Concerns\InteractsWithOrganizationWorkspace;
use App\Models\OrganizationAuditEvent;
use App\Organizations\OrganizationScopeResolver;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class OrganizationOverview extends Page
{
    use InteractsWithOrganizationWorkspace;

    protected string $view = 'filament.organization.pages.overview';

    protected static string $routePath = '/';

    protected static ?string $title = 'Organization Overview';

    protected static ?string $navigationLabel = 'Overview';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 1;

    public function unitCount(): int
    {
        return app(OrganizationScopeResolver::class)->unitsInScope()->count();
    }

    public function churchCount(): int
    {
        return app(OrganizationScopeResolver::class)->churchesInScope()->count();
    }

    public function staffCount(): ?int
    {
        return app(OrganizationScopeResolver::class)->hasRootCapability(OrganizationCapability::MembershipsView)
            ? $this->organization()->memberships()->active()->count()
            : null;
    }

    /** @return Collection<int, OrganizationAuditEvent> */
    public function recentActivity(): Collection
    {
        if (! app(OrganizationScopeResolver::class)->hasRootCapability(OrganizationCapability::OrganizationView)) {
            return new Collection;
        }

        return $this->organization()->auditEvents()->latest('occurred_at')->limit(6)->get();
    }
}
