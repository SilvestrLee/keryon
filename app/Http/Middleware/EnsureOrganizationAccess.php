<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(auth()->check(), 401);

        if ($request->routeIs('filament.organization.pages.select-organization')) {
            return $next($request);
        }

        abort_unless(app(OrganizationContext::class)->hasContext(), 403, 'Unable to determine your active Organization.');

        return $next($request);
    }
}
