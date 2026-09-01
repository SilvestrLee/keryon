<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCentralMfaReadiness
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(app()->environment('production'), 503, 'Keryon Central is unavailable until platform MFA is enforced.');

        return $next($request);
    }
}
