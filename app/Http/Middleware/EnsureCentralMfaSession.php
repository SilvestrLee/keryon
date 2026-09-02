<?php

namespace App\Http\Middleware;

use App\Filament\Central\Pages\MfaChallenge;
use App\Platform\Security\PlatformMfaSession;
use App\Support\PlatformContext;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralMfaSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $membership = app(PlatformContext::class)->currentMembership();
        abort_unless($membership, 403, 'Active platform access is required.');

        if ($request->routeIs('filament.central.auth.multi-factor-authentication.set-up-required') || $request->routeIs('filament.central.pages.mfa-challenge')) {
            return $next($request);
        }

        if (! $membership->mfaCredential?->isUsable()) {
            return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
        }

        if (! app(PlatformMfaSession::class)->isSatisfied($membership)) {
            return redirect()->guest(MfaChallenge::getUrl(panel: 'central'));
        }

        return $next($request);
    }
}
