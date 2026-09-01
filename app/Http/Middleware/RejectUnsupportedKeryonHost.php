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
        $marketingHosts = array_map('strtolower', config('public-website.marketing_hosts', []));
        $applicationHosts = array_map('strtolower', config('public-website.application_hosts', []));
        $localHosts = array_map('strtolower', config('public-website.local_hosts', []));

        if ($host === 'www.'.$baseDomain) {
            return redirect()->away(
                config('public-website.scheme').'://'.$baseDomain.$request->getRequestUri(),
                308,
            );
        }

        if ($host === 'central.'.$baseDomain) {
            abort(404);
        }

        if (! in_array($host, [...$marketingHosts, ...$applicationHosts, ...$localHosts], true)
            && ! $request->routeIs('church-website.*')
            && ! $request->routeIs('custom-church-website.*')) {
            abort(404);
        }

        return $next($request);
    }
}
