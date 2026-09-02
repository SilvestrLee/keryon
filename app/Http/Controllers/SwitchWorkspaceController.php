<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationStatus;
use App\Enums\WorkspaceType;
use App\Models\ChurchMembership;
use App\Models\OrganizationMembership;
use App\Models\PlatformMembership;
use App\Support\OrganizationContext;
use App\Support\PlatformContext;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

class SwitchWorkspaceController
{
    public function __invoke(string $type, int $workspace): RedirectResponse
    {
        $workspaceType = WorkspaceType::tryFrom($type);
        abort_unless($workspaceType !== null, 404);

        if ($workspaceType === WorkspaceType::Central) {
            $membership = PlatformMembership::query()->active()
                ->where('user_id', auth()->id())
                ->whereKey($workspace)
                ->first();
            abort_unless($membership !== null && auth()->user()->email_verified_at !== null, 403);
            session()->forget(['active_church_id', 'active_organization_id']);
            session(['active_workspace_type' => WorkspaceType::Central->value]);
            app(TenantContext::class)->forgetResolved();
            app(OrganizationContext::class)->forgetResolved();
            app(PlatformContext::class)->forgetResolved();
            app(PlatformContext::class)->currentMembership();

            return redirect(filled(config('central.domain')) ? Filament::getPanel('central')->getUrl() : '/central');
        }

        if ($workspaceType === WorkspaceType::Church) {
            $membership = ChurchMembership::query()->active()
                ->where('user_id', auth()->id())
                ->where('church_id', $workspace)
                ->whereHas('church', fn ($query) => $query->where('is_active', true))
                ->first();
            abort_unless($membership !== null, 403);
            session()->forget('active_organization_id');
            session(['active_church_id' => $membership->church_id, 'active_workspace_type' => WorkspaceType::Church->value]);
            app(OrganizationContext::class)->forgetResolved();
            app(TenantContext::class)->forgetResolved();
            app(PlatformContext::class)->forgetResolved();

            return redirect('/admin');
        }

        $membership = OrganizationMembership::query()->active()
            ->where('user_id', auth()->id())
            ->where('organization_id', $workspace)
            ->whereHas('organization', fn ($query) => $query->where('status', OrganizationStatus::ACTIVE->value))
            ->first();
        abort_unless($membership !== null, 403);
        session()->forget('active_church_id');
        session(['active_organization_id' => $membership->organization_id, 'active_workspace_type' => WorkspaceType::Organization->value]);
        app(TenantContext::class)->forgetResolved();
        app(OrganizationContext::class)->forgetResolved();
        app(PlatformContext::class)->forgetResolved();

        return redirect('/organization');
    }
}
