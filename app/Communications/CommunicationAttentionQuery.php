<?php

namespace App\Communications;

use App\Enums\ContentStatus;
use App\Filament\Pages\Campaigns;
use App\Filament\Resources\ContentItemResource;
use App\Models\CampaignCommunication;
use App\Models\Church;
use App\Models\ContentItem;
use Carbon\CarbonInterface;

final class CommunicationAttentionQuery
{
    /** @return list<CommunicationAction> */
    public function content(Church $church): array
    {
        $counts = ContentItem::query()
            ->where('church_id', $church->id)
            ->selectRaw('status, count(*) as aggregate')
            ->whereIn('status', [ContentStatus::REVIEW->value, ContentStatus::REJECTED->value])
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $actions = [];
        $review = (int) ($counts[ContentStatus::REVIEW->value] ?? 0);
        $changes = (int) ($counts[ContentStatus::REJECTED->value] ?? 0);

        if ($review > 0) {
            $actions[] = new CommunicationAction('content.review', $review.' '.($review === 1 ? 'item is' : 'items are').' awaiting review', 'Content is ready for a decision before it moves forward.', ContentItemResource::getUrl('index'), 'Review content', 'Content', $review);
        }
        if ($changes > 0) {
            $actions[] = new CommunicationAction('content.changes', $changes.' '.($changes === 1 ? 'item needs' : 'items need').' changes', 'Revise feedback-ready work in Content Studio.', ContentItemResource::getUrl('index'), 'Open Content Studio', 'Content', $changes);
        }

        return $actions;
    }

    /** @return list<CommunicationAction> */
    public function approaching(Church $church, CarbonInterface $now, CarbonInterface $through): array
    {
        $count = $church->campaignCommunications()
            ->whereNull('cancelled_at')
            ->whereBetween('target_at', [$now, $through])
            ->with('contentItem')
            ->get()
            ->filter(fn (CampaignCommunication $item): bool => $item->readiness() !== CampaignCommunication::READINESS_PREPARED)
            ->count();

        return $count === 0 ? [] : [
            new CommunicationAction('planning.approaching', $count.' planned '.($count === 1 ? 'communication needs' : 'communications need').' preparation', 'Their target times are approaching and the supporting work is not prepared yet.', Campaigns::getUrl(), 'Review plan', 'Campaigns', $count),
        ];
    }
}
