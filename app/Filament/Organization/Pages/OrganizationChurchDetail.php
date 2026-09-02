<?php

namespace App\Filament\Organization\Pages;

use App\Filament\Organization\Concerns\InteractsWithOrganizationWorkspace;
use App\Organizations\Read\Dto\OrganizationChurchSummary;
use App\Organizations\Read\OrganizationWorkspaceQuery;
use Filament\Pages\Page;

class OrganizationChurchDetail extends Page
{
    use InteractsWithOrganizationWorkspace;

    protected string $view = 'filament.organization.pages.church-detail';

    protected static ?string $slug = 'churches/{record}';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Church operational detail';

    public int $record;

    public function church(): OrganizationChurchSummary
    {
        return app(OrganizationWorkspaceQuery::class)->findChurch($this->record);
    }
}
