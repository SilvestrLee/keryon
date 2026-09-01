<?php

namespace App\Livewire;

use App\Enums\Capability;
use App\Enums\WorkspaceType;
use App\Localization\UserLocaleResolver;
use App\Search\GlobalSearchService;
use App\Support\OrganizationContext;
use App\Support\PlatformContext;
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
        $workspace = match ($workspaceType) {
            WorkspaceType::Church => app(TenantContext::class)->currentChurch(),
            WorkspaceType::Organization => app(OrganizationContext::class)->currentOrganization(),
            WorkspaceType::Central => app(PlatformContext::class)->currentMembership(),
        };
        $groups = app(GlobalSearchService::class)->search($workspaceType, $this->query);

        return view('livewire.keryon-workspace-header', [
            'workspaceType' => $workspaceType,
            'workspace' => $workspace,
            'workspaceName' => $workspaceType === WorkspaceType::Central ? 'Keryon Central' : $workspace?->name,
            'workspaces' => app(WorkspaceRegistry::class)->for(auth()->user(), $workspaceType)->groupBy(fn ($option) => $option->type->value),
            'groups' => $groups,
            'resultCount' => $groups->flatten(1)->count(),
            'minimumLength' => (int) config('keryon.search.minimum_length', 2),
            'locales' => app(UserLocaleResolver::class)->supported(),
            'currentLocale' => app()->getLocale(),
            'websiteAction' => $this->websiteAction($workspaceType, $workspace),
        ]);
    }

    /** @return array{label: string, description: string, url: string, external: bool}|null */
    private function websiteAction(WorkspaceType $workspaceType, mixed $workspace): ?array
    {
        if ($workspaceType !== WorkspaceType::Church || $workspace === null) {
            return null;
        }

        if ($url = app(ChurchPublicUrlResolver::class)->resolve($workspace)) {
            return ['label' => __('shell.go_to_website'), 'description' => __('shell.public_church_website'), 'url' => $url, 'external' => true];
        }

        if (! (app(TenantContext::class)->currentMembership()?->hasCapability(Capability::WebsiteContentView) ?? false)) {
            return null;
        }

        return ['label' => __('shell.preview_website'), 'description' => __('shell.private_website_preview'), 'url' => route('website.preview'), 'external' => true];
    }

    private function workspaceType(): WorkspaceType
    {
        return match (Filament::getCurrentPanel()?->getId()) {
            'organization' => WorkspaceType::Organization,
            'central' => WorkspaceType::Central,
            default => WorkspaceType::Church,
        };
    }
}
