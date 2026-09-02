<?php

namespace App\Organizations\Read\Dto;

final readonly class OrganizationChurchSummary
{
    /** @param list<string> $attention */
    public function __construct(
        public int $id,
        public int $assignmentId,
        public int $unitId,
        public string $name,
        public string $slug,
        public string $unitName,
        public string $unitType,
        public string $unitPath,
        public string $churchState,
        public string $onboardingState,
        public string $websiteState,
        public string $domainState,
        public array $attention,
        public bool $canManage,
    ) {}

    public function needsAttention(): bool
    {
        return $this->attention !== [];
    }
}
