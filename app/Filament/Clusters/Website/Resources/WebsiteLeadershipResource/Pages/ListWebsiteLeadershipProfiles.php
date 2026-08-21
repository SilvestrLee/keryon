<?php

namespace App\Filament\Clusters\Website\Resources\WebsiteLeadershipResource\Pages;

use App\Filament\Clusters\Website\Resources\WebsiteLeadershipResource;
use Filament\Resources\Pages\ListRecords;

class ListWebsiteLeadershipProfiles extends ListRecords
{
    protected static string $resource = WebsiteLeadershipResource::class;

    public function getTitle(): string
    {
        return 'Leadership';
    }

    public function getSubheading(): string
    {
        return 'The people your church website introduces to visitors.';
    }

    protected function getHeaderActions(): array
    {
        return [
            WebsiteLeadershipResource::createAction(),
        ];
    }
}
