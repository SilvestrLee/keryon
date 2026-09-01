<?php

namespace App\Platform\Read\Dto;

final readonly class PlatformOrganizationSummary
{
    /** @param list<array{name:string,type:string,status:string}> $firstLevelUnits */
    public function __construct(
        public int $id, public string $uuid, public string $name, public string $slug,
        public string $status, public ?string $rootUnit, public int $unitCount,
        public int $churchCount, public int $pendingAssignmentCount, public int $membershipCount,
        public array $firstLevelUnits, public string $createdAt,
    ) {}
}
