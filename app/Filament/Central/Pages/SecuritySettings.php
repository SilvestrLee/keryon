<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Security\PlatformMfaCredentialService;
use App\Platform\Security\PlatformMfaSession;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;

class SecuritySettings extends Page
{
    use InteractsWithPlatformWorkspace;

    protected string $view = 'filament.central.pages.security-settings';

    protected static ?string $title = 'Security';

    protected static ?string $navigationLabel = 'Security';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?int $navigationSort = 30;

    public string $password = '';

    /** @var list<string> */
    public array $newRecoveryCodes = [];

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::PlatformHomeView;
    }

    public function regenerate(PlatformMfaCredentialService $service): void
    {
        $this->validate(['password' => ['required', 'string']]);
        abort_unless(Hash::check($this->password, auth()->user()->password), 403, 'Password confirmation failed.');
        abort_unless(app(PlatformMfaSession::class)->isFresh($this->platformMembership()), 403, 'Verify multi-factor authentication again before changing recovery credentials.');
        /** @var AppAuthentication $provider */
        $provider = Filament::getMultiFactorAuthenticationProviders()['app'];
        $this->newRecoveryCodes = $provider->generateRecoveryCodes();
        $service->regenerateRecoveryCodes($this->platformMembership(), $this->newRecoveryCodes);
        $this->password = '';
        Notification::make()->success()->title('Recovery codes regenerated')->send();
    }
}
