<?php

namespace App\Platform\Read\Dto;

final readonly class PlatformProviderStatus
{
    /** @param list<string> $blockers */
    public function __construct(
        public string $key, public string $name, public string $category,
        public string $environment, public bool $configured, public string $governanceState,
        public string $operationalState, public ?string $lastCheckedAt, public array $blockers,
    ) {}
}
