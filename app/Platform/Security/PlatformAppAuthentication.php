<?php

namespace App\Platform\Security;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use SensitiveParameter;

class PlatformAppAuthentication extends AppAuthentication
{
    public function verifyRecoveryCode(#[SensitiveParameter] string $recoveryCode, ?HasAppAuthenticationRecovery $user = null): bool
    {
        $user ??= auth()->user();
        if (blank($user?->getAppAuthenticationRecoveryCodes())) {
            return false;
        }

        return parent::verifyRecoveryCode($recoveryCode, $user);
    }
}
