<?php

namespace App\Communications;

use App\Enums\CommunicationChannel;
use Carbon\CarbonImmutable;

final readonly class CommunicationCalendarEntry
{
    /** @param list<CommunicationAction> $actions */
    public function __construct(
        public int $id,
        public int $campaignId,
        public string $campaignTitle,
        public string $title,
        public CommunicationChannel $channel,
        public ?CarbonImmutable $targetAt,
        public bool $cancelled,
        public string $preparationKey,
        public string $preparationLabel,
        public ?string $contentStatus,
        public bool $targetPassed,
        public string $outcomeKey,
        public string $outcomeLabel,
        public ?int $websitePublicationId,
        public ?CarbonImmutable $executedAt,
        public array $actions,
    ) {}

    public function dateKey(): ?string
    {
        return $this->targetAt?->toDateString();
    }
}
