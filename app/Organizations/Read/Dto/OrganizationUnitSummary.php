<?php

namespace App\Organizations\Read\Dto;

final readonly class OrganizationUnitSummary
{
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
        public string $code,
        public string $state,
        public ?string $parentName,
        public string $path,
        public int $childUnitCount,
        public int $directChurchCount,
        public bool $canManage,
    ) {}
}
