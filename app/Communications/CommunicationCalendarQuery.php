<?php

namespace App\Communications;

use App\Enums\CommunicationChannel;
use App\Enums\ContentStatus;
use App\Filament\Clusters\Website\Pages\WebsiteOverview;
use App\Filament\Pages\CampaignWorkspace;
use App\Filament\Pages\DesignStudio;
use App\Filament\Pages\FaithFlow;
use App\Filament\Pages\WebsiteDraftHandoff;
use App\Filament\Resources\ContentItemResource;
use App\Models\CampaignCommunication;
use App\Models\Church;
use App\Website\Drafts\AvailableWebsiteDraftDestinations;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class CommunicationCalendarQuery
{
    /**
     * @return list<CommunicationCalendarEntry>
     */
    public function between(
        Church $church,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        string $timezone,
        ?string $channel = null,
        ?int $campaignId = null,
        ?string $preparation = null,
        bool $includeCancelled = false,
        bool $canManageContent = false,
        bool $canUseFaithFlow = false,
        bool $canViewDesigns = false,
        bool $canManageDesigns = false,
        bool $canUseWebsite = false,
        bool $canManageWebsite = false,
    ): array {
        $query = $church->campaignCommunications()
            ->with($this->calendarRelations())
            ->whereBetween('target_at', [
                CarbonImmutable::instance($startsAt)->utc(),
                CarbonImmutable::instance($endsAt)->utc(),
            ])
            ->orderBy('target_at')
            ->orderBy('id');

        if ($canViewDesigns) {
            $query->with('designs:id,campaign_communication_id');
        }

        $this->filters($query, $channel, $campaignId);
        if (! $includeCancelled) {
            $query->whereNull('cancelled_at');
        }

        return $query->get()
            ->map(fn (CampaignCommunication $communication): CommunicationCalendarEntry => $this->entry($communication, $timezone, $canManageContent, $canUseFaithFlow, $canViewDesigns, $canManageDesigns, $canUseWebsite, $canManageWebsite))
            ->when($preparation !== null, fn ($entries) => $entries->where('preparationKey', $preparation))
            ->values()
            ->all();
    }

    /** @return list<CommunicationCalendarEntry> */
    public function unplaced(
        Church $church,
        string $timezone,
        ?string $channel = null,
        ?int $campaignId = null,
        ?string $preparation = null,
        bool $canManageContent = false,
        bool $canUseFaithFlow = false,
        bool $canViewDesigns = false,
        bool $canManageDesigns = false,
        bool $canUseWebsite = false,
        bool $canManageWebsite = false,
        int $limit = 12,
    ): array {
        $query = $church->campaignCommunications()
            ->with($this->calendarRelations())
            ->whereNull('target_at')
            ->whereNull('cancelled_at')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        if ($canViewDesigns) {
            $query->with('designs:id,campaign_communication_id');
        }

        $this->filters($query, $channel, $campaignId);

        return $query->limit($limit)->get()
            ->map(fn (CampaignCommunication $communication): CommunicationCalendarEntry => $this->entry($communication, $timezone, $canManageContent, $canUseFaithFlow, $canViewDesigns, $canManageDesigns, $canUseWebsite, $canManageWebsite))
            ->when($preparation !== null, fn ($entries) => $entries->where('preparationKey', $preparation))
            ->values()
            ->all();
    }

    private function filters(mixed $query, ?string $channel, ?int $campaignId): void
    {
        if ($channel !== null && CommunicationChannel::tryFrom($channel) !== null) {
            $query->where('channel', $channel);
        }
        if ($campaignId !== null) {
            $query->where('campaign_id', $campaignId);
        }
    }

    /** @return array<string, mixed> */
    private function calendarRelations(): array
    {
        return [
            'campaign:id,title,status,starts_on,ends_on',
            'contentItem:id,title,content_type,status,deleted_at',
            'websiteContentProvenances:id,church_id,campaign_communication_id,destination,website_record_id,applied_at',
            'websiteContentProvenances.publicationAttributions:id,church_id,website_content_provenance_id,website_publication_id',
            'websiteContentProvenances.publicationAttributions.publication:id,church_id,published_at',
            'supersededWebsiteContentProvenances:id,church_id,campaign_communication_id',
        ];
    }

    private function entry(CampaignCommunication $communication, string $timezone, bool $canManageContent, bool $canUseFaithFlow, bool $canViewDesigns, bool $canManageDesigns, bool $canUseWebsite, bool $canManageWebsite): CommunicationCalendarEntry
    {
        $targetAt = $communication->target_at === null
            ? null
            : CarbonImmutable::instance($communication->target_at)->setTimezone($timezone);
        $actions = [];
        [$outcomeKey, $outcomeLabel, $publicationId, $executedAt] = $this->outcome($communication, $targetAt);

        if ($communication->contentItem !== null) {
            $actions[] = new CommunicationAction(
                'content.open',
                'Continue content',
                'Open the canonical Content Studio record.',
                ContentItemResource::getUrl($canManageContent ? 'edit' : 'view', ['record' => $communication->contentItem]),
                $canManageContent ? 'Continue content' : 'View content',
                'Content',
            );
        } else {
            $actions[] = new CommunicationAction(
                'campaign.open',
                'Open Campaign',
                'Continue the communication plan in its Campaign workspace.',
                CampaignWorkspace::getUrl(['campaign' => $communication->campaign_id]),
                'Open Campaign',
                'Campaign',
            );
            if ($canUseFaithFlow) {
                $actions[] = new CommunicationAction('faithflow.start', 'Develop with FaithFlow', 'Open FaithFlow with this Campaign communication context.', FaithFlow::getUrl(['campaignCommunicationId' => $communication->id]), 'Use FaithFlow', 'FaithFlow');
            }
        }

        if ($canViewDesigns && $communication->relationLoaded('designs') && $communication->designs->isNotEmpty()) {
            $actions[] = new CommunicationAction('design.open', 'Continue design', 'Open the existing Design linked to this communication.', DesignStudio::getUrl(['design' => $communication->designs->first()->id]), 'View design', 'Design');
        } elseif ($canManageDesigns) {
            $actions[] = new CommunicationAction('design.create', 'Create design', 'Start Design work with Campaign context preserved.', DesignStudio::getUrl(['campaignCommunicationId' => $communication->id]), 'Create design', 'Design');
        }

        if ($canUseWebsite && $communication->channel === CommunicationChannel::WEBSITE) {
            $canHandoff = $canManageWebsite
                && $communication->contentItem?->status === ContentStatus::APPROVED
                && app(AvailableWebsiteDraftDestinations::class)->for($communication->contentItem->content_type) !== [];
            $actions[] = $canHandoff
                ? new CommunicationAction('website.apply', 'Apply to Website draft', 'Prepare Website-owned content without publishing it.', WebsiteDraftHandoff::getUrl(['content' => $communication->content_item_id, 'communication' => $communication->id]), 'Apply to Website draft', 'Website')
                : new CommunicationAction('website.continue', 'Continue in Website', 'Prepare Website-owned content without implying publication.', WebsiteOverview::getUrl(), 'Continue in Website', 'Website');
            if ($publicationId !== null) {
                $actions[] = new CommunicationAction('website.publication', 'View publication evidence', 'Open the Website publication history and attributable execution context.', WebsiteOverview::getUrl().'#website-publication-status', 'View publication', 'Website');
            }
        }

        return new CommunicationCalendarEntry(
            $communication->id,
            $communication->campaign_id,
            $communication->campaign->title,
            $communication->title,
            $communication->channel,
            $targetAt,
            $communication->cancelled_at !== null,
            $communication->readiness(),
            $this->readinessLabel($communication->readiness()),
            $communication->contentItem?->status?->label(),
            $targetAt !== null && $targetAt->isPast(),
            $outcomeKey,
            $outcomeLabel,
            $publicationId,
            $executedAt,
            $actions,
        );
    }

    /** @return array{string, string, int|null, CarbonImmutable|null} */
    private function outcome(CampaignCommunication $communication, ?CarbonImmutable $targetAt): array
    {
        if ($communication->cancelled_at !== null) {
            return ['cancelled', 'Cancelled', null, null];
        }

        if ($communication->channel === CommunicationChannel::WEBSITE) {
            $attribution = $communication->websiteContentProvenances
                ->flatMap->publicationAttributions
                ->sortByDesc(fn ($item) => $item->publication?->published_at)
                ->first(fn ($item) => $item->publication !== null);

            if ($attribution?->publication !== null) {
                return [
                    'published',
                    'Published / completed',
                    $attribution->publication->id,
                    CarbonImmutable::instance($attribution->publication->published_at),
                ];
            }

            if ($communication->supersededWebsiteContentProvenances->isNotEmpty()) {
                return ['superseded', 'Superseded', null, null];
            }
        }

        if ($targetAt?->isPast()) {
            return ['overdue', 'Overdue / not executed', null, null];
        }
        if ($targetAt?->isToday()) {
            return ['due', 'Due', null, null];
        }
        if ($communication->readiness() === CampaignCommunication::READINESS_PREPARED) {
            return ['ready', 'Ready', null, null];
        }

        return ['planned', 'Planned', null, null];
    }

    public function readinessLabel(string $readiness): string
    {
        return match ($readiness) {
            CampaignCommunication::READINESS_NOT_STARTED => 'Not started',
            CampaignCommunication::READINESS_IN_PREPARATION => 'In preparation',
            CampaignCommunication::READINESS_AWAITING_APPROVAL => 'Awaiting approval',
            CampaignCommunication::READINESS_PREPARED => 'Prepared',
            CampaignCommunication::READINESS_OUTSTANDING => 'Outstanding',
            CampaignCommunication::READINESS_CANCELLED => 'Cancelled',
            default => 'Not started',
        };
    }
}
