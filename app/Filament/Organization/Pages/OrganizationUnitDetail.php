<?php

namespace App\Filament\Organization\Pages;

use App\Filament\Organization\Concerns\InteractsWithOrganizationWorkspace;
use App\Organizations\Read\Dto\OrganizationChurchSummary;
use App\Organizations\Read\Dto\OrganizationUnitSummary;
use App\Organizations\Read\OrganizationWorkspaceQuery;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

class OrganizationUnitDetail extends Page
{
    use InteractsWithOrganizationWorkspace, WithPagination;

    protected string $view = 'filament.organization.pages.unit-detail';

    protected static ?string $slug = 'units/{record}';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Organization Unit';

    public int $record;

    public function unit(): OrganizationUnitSummary
    {
        return app(OrganizationWorkspaceQuery::class)->findUnit($this->record);
    }

    public function children(): LengthAwarePaginator
    {
        return app(OrganizationWorkspaceQuery::class)->paginateChildUnits($this->record);
    }

    public function churches(): LengthAwarePaginator
    {
        return app(OrganizationWorkspaceQuery::class)->paginateChurchesBelowUnit($this->record);
    }

    public function unitUrl(OrganizationUnitSummary $unit): string
    {
        return self::getUrl(['record' => $unit->id], panel: 'organization');
    }

    public function churchUrl(OrganizationChurchSummary $church): string
    {
        return OrganizationChurchDetail::getUrl(['record' => $church->id], panel: 'organization');
    }
}
