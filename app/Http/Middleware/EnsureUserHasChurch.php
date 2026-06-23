<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasChurch
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && ! auth()->user()->church_id) {
            if (! $request->is('admin/setup')) {
                return redirect('/admin/setup');
            }
        }

        return $next($request);
    }
}
