<?php

namespace App\Platform\Read\Dto;

final readonly class PlatformChurchSummary
{
    public function __construct(
        public int $id, public string $name, public string $slug, public bool $active,
        public ?string $country, public string $timezone, public ?string $activatedAt, public string $createdAt,
        public ?string $primaryName, public ?string $primaryEmail, public ?string $primaryStatus,
        public ?string $organizationName, public ?string $organizationUnit, public ?string $assignmentStatus,
        public ?string $subscriptionStatus, public ?string $trialEndsAt, public ?string $planVersion,
        public bool $websitePublished, public ?string $websitePublishedAt, public ?string $publicUrl,
        public string $domainState, public ?string $activationStatus,
    ) {}
}
