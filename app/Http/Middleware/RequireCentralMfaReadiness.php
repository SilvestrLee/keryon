<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCentralMfaReadiness
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production')) {
            abort_unless(filled(config('central.domain')), 503, 'Keryon Central production host is not configured.');
            abort_unless($request->getHost() === config('central.domain'), 404);
            abort_unless(config('session.secure') === true, 503, 'Keryon Central requires secure session cookies.');
        }

        return $next($request);
    }
}
