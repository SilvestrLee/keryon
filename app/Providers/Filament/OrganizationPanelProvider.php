<?php

namespace App\Providers\Filament;

use App\Filament\Organization\Pages\OrganizationOverview;
use App\Http\Middleware\AuthenticateOrganizationWorkspace;
use App\Http\Middleware\EnsureOrganizationAccess;
use App\Http\Middleware\ResolveOrganizationContext;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class OrganizationPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('organization')
            ->path('organization')
            ->viteTheme('resources/css/filament/organization/theme.css')
            ->darkMode(false)
            ->brandLogo(fn () => view('components.keryon-logo'))
            ->brandLogoHeight('2.25rem')
            ->login()
            ->colors(['primary' => Color::Amber])
            ->discoverPages(
                in: app_path('Filament/Organization/Pages'),
                for: 'App\\Filament\\Organization\\Pages',
            )
            ->pages([OrganizationOverview::class])
            ->widgets([AccountWidget::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticateOrganizationWorkspace::class,
                ResolveOrganizationContext::class,
                EnsureOrganizationAccess::class,
            ])
            ->strictAuthorization();
    }
}
