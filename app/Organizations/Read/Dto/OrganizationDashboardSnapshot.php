<?php

namespace App\Organizations\Read\Dto;

final readonly class OrganizationDashboardSnapshot
{
    /**
     * @param  list<array{path:string,responsibilities:list<string>}>  $scopes
     * @param  list<OrganizationChurchSummary>  $attentionItems
     * @param  list<OrganizationChurchSummary>  $churchPreview
     * @param  list<OrganizationUnitSummary>  $unitPreview
     * @param  list<array{title:string,detail:string,status:string}>  $communicationItems
     * @param  list<array{title:string,detail:string,status:string}>  $campaignItems
     * @param  list<array{label:string,occurred_at:string}>  $recentActivity
     */
    public function __construct(
        public string $organizationName,
        public string $organizationState,
        public array $scopes,
        public int $unitCount,
        public int $churchCount,
        public int $activeChurchCount,
        public int $attentionCount,
        public int $onboardingAttentionCount,
        public int $websiteLiveCount,
        public int $websiteAttentionCount,
        public int $domainConnectedCount,
        public int $domainAttentionCount,
        public int $keryonAddressCount,
        public ?int $peopleCount,
        public array $attentionItems,
        public array $churchPreview,
        public array $unitPreview,
        public array $communicationItems,
        public array $campaignItems,
        public array $recentActivity,
    ) {}
}
