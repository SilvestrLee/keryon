<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectUnsupportedKeryonHost
{
    /**
     * Prevent unsupported first-party subdomains from falling through to
     * Keryon's host-agnostic marketing routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $baseDomain = strtolower((string) config('public-website.base_domain'));
        $host = strtolower($request->getHost());
        $reservedHosts = array_map(
            fn (string $label): string => "{$label}.{$baseDomain}",
            config('public-website.reserved_subdomains', []),
        );

        if (
            $host !== $baseDomain
            && str_ends_with($host, ".{$baseDomain}")
            && ! in_array($host, $reservedHosts, true)
            && ! $request->routeIs('church-website.*')
        ) {
            abort(404);
        }

        return $next($request);
    }
}
