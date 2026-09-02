<?php

namespace App\Filament\Organization\Pages;

use App\Filament\Organization\Concerns\InteractsWithOrganizationWorkspace;
use App\Organizations\Read\Dto\OrganizationDashboardSnapshot;
use App\Organizations\Read\OrganizationWorkspaceQuery;
use Filament\Pages\Page;

class OrganizationOverview extends Page
{
    use InteractsWithOrganizationWorkspace;

    protected string $view = 'filament.organization.pages.overview';

    protected static string $routePath = '/';

    protected static ?string $title = 'Organization Home';

    protected static ?string $navigationLabel = 'Home';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = 1;

    public function dashboard(): OrganizationDashboardSnapshot
    {
        return app(OrganizationWorkspaceQuery::class)->dashboard();
    }
}
