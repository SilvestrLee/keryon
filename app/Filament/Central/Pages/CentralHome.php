<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Models\PlatformAuditEvent;
use App\Platform\Read\PlatformOperationsSnapshotBuilder;
use App\Support\PlatformContext;
use Filament\Pages\Page;

class CentralHome extends Page
{
    use InteractsWithPlatformWorkspace;

    protected string $view = 'filament.central.pages.home';

    protected static string $routePath = '/';

    protected static ?string $title = 'Keryon Central';

    protected static ?string $navigationLabel = 'Home';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = 1;

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::PlatformHomeView;
    }

    public function attention(): array
    {
        return app(PlatformOperationsSnapshotBuilder::class)->build();
    }

    public function recentActivity()
    {
        return app(PlatformContext::class)->hasCapability(PlatformCapability::PlatformAuditView) ? PlatformAuditEvent::query()->with('actor:id,name')->latest('occurred_at')->limit(8)->get() : collect();
    }
}
