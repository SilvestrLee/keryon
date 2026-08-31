<?php

namespace App\Livewire;

use App\Enums\WorkspaceType;
use App\Localization\UserLocaleResolver;
use App\Search\GlobalSearchService;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use App\Website\ChurchPublicUrlResolver;
use App\Workspace\WorkspaceRegistry;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class KeryonWorkspaceHeader extends Component
{
    public string $query = '';

    public bool $searchOpen = false;

    public function openSearch(): void
    {
        $this->searchOpen = true;
    }

    public function closeSearch(): void
    {
        $this->searchOpen = false;
        $this->query = '';
    }

    public function render(): View
    {
        $workspaceType = $this->workspaceType();
        $workspace = $workspaceType === WorkspaceType::Church
            ? app(TenantContext::class)->currentChurch()
            : app(OrganizationContext::class)->currentOrganization();
        $groups = app(GlobalSearchService::class)->search($workspaceType, $this->query);

        return view('livewire.keryon-workspace-header', [
            'workspaceType' => $workspaceType,
            'workspace' => $workspace,
            'workspaces' => app(WorkspaceRegistry::class)->for(auth()->user(), $workspaceType)->groupBy(fn ($option) => $option->type->value),
            'groups' => $groups,
            'resultCount' => $groups->flatten(1)->count(),
            'minimumLength' => (int) config('keryon.search.minimum_length', 2),
            'locales' => app(UserLocaleResolver::class)->supported(),
            'currentLocale' => app()->getLocale(),
            'websiteUrl' => $workspaceType === WorkspaceType::Church && $workspace !== null
                ? app(ChurchPublicUrlResolver::class)->resolve($workspace)
                : null,
        ]);
    }

    private function workspaceType(): WorkspaceType
    {
        return Filament::getCurrentPanel()?->getId() === 'organization'
            ? WorkspaceType::Organization
            : WorkspaceType::Church;
    }
}
