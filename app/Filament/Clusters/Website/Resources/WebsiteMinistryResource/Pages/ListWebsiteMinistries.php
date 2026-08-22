<?php

namespace App\Filament\Clusters\Website\Resources\WebsiteMinistryResource\Pages;

use App\Filament\Clusters\Website\Resources\WebsiteMinistryResource;
use Filament\Resources\Pages\ListRecords;

class ListWebsiteMinistries extends ListRecords
{
    protected static string $resource = WebsiteMinistryResource::class;

    public function getTitle(): string
    {
        return 'Ministries';
    }

    public function getSubheading(): string
    {
        return 'The ministries your church website shows to visitors.';
    }

    protected function getHeaderActions(): array
    {
        return [
            WebsiteMinistryResource::createAction(),
        ];
    }
}
