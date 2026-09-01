<?php

namespace App\Http\Middleware;

use App\Enums\WorkspaceType;
use App\Support\OrganizationContext;
use App\Support\PlatformContext;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePlatformContext
{
    public function handle(Request $request, Closure $next): Response
    {
        session()->forget(['active_church_id', 'active_organization_id']);
        session(['active_workspace_type' => WorkspaceType::Central->value]);
        app(TenantContext::class)->forgetResolved();
        app(OrganizationContext::class)->forgetResolved();
        app(PlatformContext::class)->forgetResolved();
        app(PlatformContext::class)->currentMembership();

        return $next($request);
    }
}
