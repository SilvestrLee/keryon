<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Security\PlatformMfaSession;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class MfaChallenge extends Page
{
    use InteractsWithPlatformWorkspace;

    protected string $view = 'filament.central.pages.mfa-challenge';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'mfa-challenge';

    public string $code = '';

    public bool $useRecoveryCode = false;

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::PlatformHomeView;
    }

    public function verify(): void
    {
        $membership = $this->platformMembership();
        $this->validate(['code' => ['required', 'string', 'max:64']]);
        $key = 'central-mfa-'.($this->useRecoveryCode ? 'recovery' : 'otp').':'.$membership->id.':'.request()->ip();
        $decay = $this->useRecoveryCode ? 900 : 60;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Try again later.']);
        }

        /** @var AppAuthentication $provider */
        $provider = Filament::getMultiFactorAuthenticationProviders()['app'];
        $valid = $this->useRecoveryCode
            ? $provider->verifyRecoveryCode(trim($this->code), auth()->user())
            : $provider->verifyCode(preg_replace('/\D/', '', $this->code), shouldPreventCodeReuse: true);

        if (! $valid) {
            RateLimiter::hit($key, $decay);
            throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
        }

        RateLimiter::clear($key);
        app(PlatformMfaSession::class)->markVerified($membership->fresh('mfaCredential'));
        $this->redirect(CentralHome::getUrl(panel: 'central'));
    }

    public function toggleRecovery(): void
    {
        $this->useRecoveryCode = ! $this->useRecoveryCode;
        $this->code = '';
        $this->resetErrorBag();
    }
}
