<?php

namespace App\Http\Middleware;

use App\PublicWebsite\PublicWebsiteContext;
use App\PublicWebsite\PublicWebsiteHostResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePublicWebsite
{
    public function __construct(
        private readonly PublicWebsiteHostResolver $resolver,
        private readonly PublicWebsiteContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $resolved = $this->resolver->resolve($request->getHost());

        abort_if($resolved === null, 404);

        $this->context->resolve($resolved->church, $resolved);

        return $next($request);
    }
}
