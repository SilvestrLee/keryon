<?php

namespace App\Http\Middleware;

use App\PublicWebsite\PublicWebsiteContext;
use App\PublicWebsite\PublicWebsiteResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePublicWebsite
{
    public function __construct(
        private readonly PublicWebsiteResolver $resolver,
        private readonly PublicWebsiteContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('church');
        $church = is_string($slug) ? $this->resolver->resolve($slug) : null;

        abort_if($church === null, 404);

        $this->context->resolve($church);

        return $next($request);
    }
}
