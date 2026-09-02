<?php

namespace App\Filament\Organization\Concerns;

use App\Models\Organization;
use App\Organizations\Read\OrganizationWorkspaceQuery;
use App\Support\OrganizationContext;
use Filament\Notifications\Notification;
use Throwable;

trait InteractsWithOrganizationWorkspace
{
    public static function canAccess(): bool
    {
        return app(OrganizationContext::class)->hasContext();
    }

    public function organization(): Organization
    {
        return app(OrganizationContext::class)->currentOrganization()
            ?? abort(403, 'Unable to determine your active Organization.');
    }

    public function canSwitchOrganization(): bool
    {
        return auth()->user()?->activeOrganizationMemberships()
            ->whereHas('organization', fn ($query) => $query->where('status', 'active'))
            ->count() > 1;
    }

    /** @return list<array{path:string,responsibilities:list<string>}> */
    public function organizationScopes(): array
    {
        return app(OrganizationWorkspaceQuery::class)->scopePresentation();
    }

    protected function runGoverned(callable $operation, string $success): mixed
    {
        try {
            $result = $operation();
            Notification::make()->success()->title($success)->send();

            return $result;
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->danger()
                ->title('Action unavailable')
                ->body($exception instanceof \DomainException
                    ? $exception->getMessage()
                    : 'Your authority or the Organization state changed. Refresh and try again.')
                ->send();

            return null;
        }
    }
}
