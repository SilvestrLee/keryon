<?php

namespace App\Providers\Filament;

use App\Filament\Central\Auth\CentralLogin;
use App\Filament\Central\Pages\CentralHome;
use App\Filament\Pages\AccountProfile;
use App\Http\Middleware\ApplyUserLocale;
use App\Http\Middleware\CentralSecurityHeaders;
use App\Http\Middleware\EnsureCentralMfaSession;
use App\Http\Middleware\EnsurePlatformAccess;
use App\Http\Middleware\RequireCentralMfaReadiness;
use App\Http\Middleware\ResolvePlatformContext;
use App\Platform\Security\PlatformAppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CentralPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('central')
            ->path('central')
            ->domain(config('central.domain'))
            ->viteTheme('resources/css/filament/central/theme.css')
            ->darkMode(false)
            ->brandLogo(fn () => view('components.keryon-logo'))
            ->brandLogoHeight('2.25rem')
            ->login(CentralLogin::class)
            ->passwordReset()
            ->userMenu(false)
            ->multiFactorAuthentication(
                PlatformAppAuthentication::make()->recoverable()->regenerableRecoveryCodes(false)->recoveryCodeCount(10)->brandName('Keryon Central'),
                isRequired: true,
            )
            ->colors(['primary' => Color::Amber])
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn () => view('filament.partials.workspace-header'))
            ->discoverPages(in: app_path('Filament/Central/Pages'), for: 'App\\Filament\\Central\\Pages')
            ->pages([CentralHome::class, AccountProfile::class])
            ->widgets([AccountWidget::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ApplyUserLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                CentralSecurityHeaders::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RequireCentralMfaReadiness::class,
                ResolvePlatformContext::class,
                EnsurePlatformAccess::class,
                EnsureCentralMfaSession::class,
            ])
            ->strictAuthorization();
    }
}
