<?php

namespace App\Campaigns;

use App\Models\ContentItem;
use Illuminate\Support\Facades\DB;

class CreateCampaignContentItem
{
    public function __construct(
        private readonly CampaignCommunicationContext $context,
        private readonly CampaignCommunicationManager $communications,
    ) {}

    /** @param array{title: string, content_type: mixed, body: string} $attributes */
    public function handle(int $communicationId, array $attributes): ContentItem
    {
        return DB::transaction(function () use ($communicationId, $attributes): ContentItem {
            $communication = $this->context->forContentCreation($communicationId);

            $contentItem = new ContentItem($attributes);
            $contentItem->save();

            $this->communications->linkContentItem($communication, $contentItem);

            return $contentItem;
        });
    }
}
