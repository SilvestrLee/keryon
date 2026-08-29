<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;

class AuthenticateOrganizationWorkspace extends Authenticate
{
    /**
     * Authenticate the Organization panel without changing the existing
     * Church panel's user contract. Organization authority is evaluated by
     * the context and access middleware that follow this authentication gate.
     *
     * @param  array<string>  $guards
     */
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
