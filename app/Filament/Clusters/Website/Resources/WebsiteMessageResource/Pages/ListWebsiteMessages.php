<?php

namespace App\Filament\Clusters\Website\Resources\WebsiteMessageResource\Pages;

use App\Filament\Clusters\Website\Resources\WebsiteMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListWebsiteMessages extends ListRecords
{
    protected static string $resource = WebsiteMessageResource::class;

    public function getTitle(): string
    {
        return 'Messages';
    }

    public function getSubheading(): string
    {
        return 'Sermons and messages visitors can watch or listen to.';
    }

    protected function getHeaderActions(): array
    {
        return [
            WebsiteMessageResource::createAction(),
        ];
    }
}
