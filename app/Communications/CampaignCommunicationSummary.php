<?php

namespace App\Communications;

final readonly class CampaignCommunicationSummary
{
    /** @param array<string, int> $preparation */
    public function __construct(
        public int $id,
        public string $title,
        public string $status,
        public string $period,
        public int $plannedCount,
        public array $preparation,
        public string $destination,
    ) {}
}
