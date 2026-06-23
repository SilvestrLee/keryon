<?php

namespace App\Filament\Resources\CongregationResource\Pages;

use App\Filament\Resources\CongregationResource;
use App\Filament\Widgets\CongregationStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCongregationMembers extends ListRecords
{
    protected static string $resource = CongregationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->slideOver(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CongregationStatsWidget::class,
        ];
    }
}
