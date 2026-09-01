<?php

namespace App\PublicWebsite;

use App\Enums\PublicWebsiteHostType;
use App\Models\Church;
use App\Models\ChurchDomain;

final readonly class ResolvedPublicWebsiteHost
{
    public function __construct(
        public Church $church,
        public string $hostname,
        public PublicWebsiteHostType $type,
        public ?ChurchDomain $domain = null,
    ) {}

    public function isAlias(): bool
    {
        return $this->type === PublicWebsiteHostType::CustomDomain && ! ($this->domain?->is_primary ?? false);
    }
}
