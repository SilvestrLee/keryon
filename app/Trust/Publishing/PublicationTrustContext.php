<?php

namespace App\Trust\Publishing;

use App\Enums\PublicationAiReviewStatus;
use App\Enums\PublicationDestination;
use App\Models\Church;
use App\Models\ChurchMembership;

final readonly class PublicationTrustContext
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, int>  $assetIds
     * @param  array<string, string>  $renditionUuids
     */
    public function __construct(
        public PublicationDestination $destination,
        public ChurchMembership $membership,
        public Church $church,
        public array $snapshot,
        public array $assetIds,
        public array $renditionUuids,
        public PublicationAiReviewStatus $aiReviewStatus = PublicationAiReviewStatus::Unknown,
    ) {}
}
