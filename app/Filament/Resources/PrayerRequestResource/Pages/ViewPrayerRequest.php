<?php

namespace App\Filament\Resources\PrayerRequestResource\Pages;

use App\Filament\Resources\PrayerRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPrayerRequest extends ViewRecord
{
    protected static string $resource = PrayerRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
