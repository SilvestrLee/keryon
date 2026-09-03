<?php

namespace App\Organizations\Communications\Read\Dto;

final class OrganizationCommunicationSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $kind,
        public readonly string $kindLabel,
        public readonly string $title,
        public readonly string $displayState,
        public readonly string $displayStateLabel,
        public readonly string $governingUnitName,
        public readonly string $governingUnitPath,
        public readonly int $revisionVersion,
        public readonly int $materialsCount,
        public readonly int $assetsCount,
        public readonly string $updatedAt,
        public readonly bool $canEdit,
        public readonly bool $canApprove,
    ) {}
}
