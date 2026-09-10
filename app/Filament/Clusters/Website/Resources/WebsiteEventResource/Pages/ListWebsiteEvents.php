<?php

namespace App\Filament\Clusters\Website\Resources\WebsiteEventResource\Pages;

use App\Filament\Clusters\Website\Resources\WebsiteEventResource;
use Filament\Resources\Pages\ListRecords;

class ListWebsiteEvents extends ListRecords
{
    protected static string $resource = WebsiteEventResource::class;

    public function getTitle(): string
    {
        return 'Events';
    }

    public function getSubheading(): string
    {
        return 'Upcoming events your church website shows to visitors.';
    }

    protected function getHeaderActions(): array
    {
        return [
            WebsiteEventResource::createAction(),
        ];
    }
}
