<?php

namespace App\Website\Drafts;

use App\Enums\WebsiteDraftDestination;
use App\Models\WebsiteContentProvenance;

final readonly class WebsiteDraftHandoffResult
{
    public function __construct(
        public WebsiteDraftDestination $destination,
        public WebsiteContentProvenance $provenance,
        public bool $alreadyApplied,
    ) {}
}
