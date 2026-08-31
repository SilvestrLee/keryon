<?php

namespace App\Communications;

final readonly class ChurchCommunicationsSnapshot
{
    /**
     * @param  list<CommunicationAction>  $actions
     * @param  list<CommunicationCalendarEntry>  $today
     * @param  array<string, list<CommunicationCalendarEntry>>  $week
     * @param  list<CommunicationCalendarEntry>  $unplaced
     * @param  list<CampaignCommunicationSummary>  $campaigns
     * @param  list<array{label: string, description: string, destination: string}>  $destinations
     * @param  array{total: int, prepared: int, awaiting_approval: int, in_preparation: int, not_started: int, outstanding: int}  $weekCounts
     */
    public function __construct(
        public string $churchName,
        public string $timezone,
        public array $actions,
        public array $today,
        public array $week,
        public array $unplaced,
        public array $campaigns,
        public array $destinations,
        public array $weekCounts,
        public bool $canManageCampaigns,
    ) {}
}
