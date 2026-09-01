<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;

class AuthenticatePlatformWorkspace extends Authenticate
{
    /** @param array<string> $guards */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();
        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return;
        }

        $this->auth->shouldUse(Filament::getAuthGuard());
    }
}
