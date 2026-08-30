<?php

namespace App\Filament\Pages;

use App\Dashboard\ChurchDashboardSnapshotBuilder;
use App\Dashboard\DashboardSnapshot;
use Filament\Pages\Dashboard;

class ChurchDashboard extends Dashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Home';

    protected static ?string $title = 'Home';

    protected static ?int $navigationSort = -2;

    protected string $view = 'filament.pages.church-dashboard';

    /** @return array{snapshot: DashboardSnapshot} */
    public function getViewData(): array
    {
        return ['snapshot' => app(ChurchDashboardSnapshotBuilder::class)->build()];
    }
}
