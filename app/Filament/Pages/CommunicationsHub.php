<?php

namespace App\Filament\Pages;

use App\Communications\ChurchCommunicationsSnapshot;
use App\Communications\ChurchCommunicationsSnapshotBuilder;
use App\Support\TenantContext;
use Filament\Pages\Page;

class CommunicationsHub extends Page
{
    protected string $view = 'filament.pages.communications-hub';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Communications';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Communications';

    protected static ?string $slug = 'communications';

    protected static ?int $navigationSort = 0;

    public static function canAccess(): bool
    {
        $membership = app(TenantContext::class)->currentMembership();

        return $membership !== null
            && ChurchCommunicationsSnapshotBuilder::hasRelevantCapability($membership->capabilities());
    }

    /** @return array{snapshot: ChurchCommunicationsSnapshot} */
    public function getViewData(): array
    {
        return ['snapshot' => app(ChurchCommunicationsSnapshotBuilder::class)->build()];
    }
}
