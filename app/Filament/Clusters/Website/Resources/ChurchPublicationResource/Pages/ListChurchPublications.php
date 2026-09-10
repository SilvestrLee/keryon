<?php

namespace App\Filament\Clusters\Website\Resources\ChurchPublicationResource\Pages;

use App\Filament\Clusters\Website\Resources\ChurchPublicationResource;
use Filament\Resources\Pages\ListRecords;

class ListChurchPublications extends ListRecords
{
    protected static string $resource = ChurchPublicationResource::class;

    public function getTitle(): string
    {
        return 'Publications';
    }

    public function getSubheading(): string
    {
        return 'Books and resources your church or pastor has published.';
    }

    protected function getHeaderActions(): array
    {
        return [
            ChurchPublicationResource::createAction(),
        ];
    }
}
