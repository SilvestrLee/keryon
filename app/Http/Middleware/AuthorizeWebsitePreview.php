<?php

namespace App\Http\Middleware;

use App\Enums\Capability;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeWebsitePreview
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect('/admin/login');
        }

        abort_unless(
            $this->tenant->currentMembership()?->hasCapability(Capability::WebsiteContentView) ?? false,
            403,
        );

        return $next($request);
    }
}
