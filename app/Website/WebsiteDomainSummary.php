<?php

namespace App\Website;

final readonly class WebsiteDomainSummary
{
    public function __construct(
        public string $state,
        public string $headline,
        public string $detail,
        public ?string $hostname,
        public string $keryonUrl,
        public ?string $officialUrl,
        public bool $hasHealthyCustomPrimary,
    ) {}
}
