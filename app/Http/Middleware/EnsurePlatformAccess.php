<?php

namespace App\Http\Middleware;

use App\Support\PlatformContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(auth()->check(), 401);
        abort_unless(auth()->user()->email_verified_at !== null, 403, 'Keryon Central requires a verified email address.');
        abort_unless(app(PlatformContext::class)->hasContext(), 403, 'Active platform access is required.');

        return $next($request);
    }
}
