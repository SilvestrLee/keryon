<?php

namespace App\Filament\Central\Auth;

use App\Platform\Security\PlatformMfaSession;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Schemas\Components\Component;

class CentralLogin extends Login
{
    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response !== null && auth()->check()) {
            $membership = auth()->user()->platformMembership()->active()->with('mfaCredential')->first();
            if ($membership?->mfaCredential?->isUsable()) {
                app(PlatformMfaSession::class)->markVerified($membership);
            }
        }

        return $response;
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()->hidden()->default(false);
    }
}
