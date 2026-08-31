<?php

namespace App\Website\Drafts;

use App\Enums\ContentType;
use App\Enums\WebsiteDraftDestination;

final class AvailableWebsiteDraftDestinations
{
    /** @return list<WebsiteDraftDestination> */
    public function for(ContentType $type): array
    {
        return array_values(array_filter(
            WebsiteDraftDestination::cases(),
            fn (WebsiteDraftDestination $destination): bool => in_array($type, $destination->compatibleTypes(), true),
        ));
    }

    public function supports(ContentType $type, WebsiteDraftDestination $destination): bool
    {
        return in_array($destination, $this->for($type), true);
    }
}
