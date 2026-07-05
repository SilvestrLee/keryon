<?php

namespace App\Filament\Resources\CongregationResource\Pages;

use App\Filament\Resources\CongregationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCongregationMember extends ViewRecord
{
    protected static string $resource = CongregationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
