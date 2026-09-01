<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use App\Support\PlatformContext;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        session()->forget('active_church_id');
        session(['active_workspace_type' => 'organization']);
        app(TenantContext::class)->forgetResolved();
        app(PlatformContext::class)->forgetResolved();
        app(OrganizationContext::class)->currentMembership();

        return $next($request);
    }
}
