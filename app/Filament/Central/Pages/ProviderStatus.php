<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Read\PlatformProviderStatusQuery;
use Filament\Pages\Page;

class ProviderStatus extends Page
{
    use InteractsWithPlatformWorkspace;

    protected string $view = 'filament.central.pages.providers';

    protected static ?string $title = 'Provider Status';

    protected static string|\UnitEnum|null $navigationGroup = 'Trust & Infrastructure';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::ProvidersView;
    }

    public function statuses(): array
    {
        return app(PlatformProviderStatusQuery::class)->all();
    }
}
