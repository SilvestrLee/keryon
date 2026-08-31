<?php

namespace App\Http\Middleware;

use App\Localization\UserLocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(app(UserLocaleResolver::class)->resolve($request->user()));

        return $next($request);
    }
}
