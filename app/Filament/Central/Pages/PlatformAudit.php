<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Models\PlatformAuditEvent;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class PlatformAudit extends Page
{
    use InteractsWithPlatformWorkspace;

    protected string $view = 'filament.central.pages.audit';

    protected static ?string $title = 'Platform Audit';

    protected static ?string $navigationLabel = 'Audit';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 30;

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::PlatformAuditView;
    }

    public function events(): Collection
    {
        abort_unless(auth()->user()->can('viewAny', PlatformAuditEvent::class), 403);

        return PlatformAuditEvent::query()->with('actor:id,name,email')->latest('occurred_at')->limit(100)->get();
    }
}
