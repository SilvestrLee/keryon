<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        app(OrganizationContext::class)->currentMembership();

        return $next($request);
    }
}
