<?php

namespace App\Communications;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Enums\CampaignStatus;
use App\Enums\Capability;
use App\Enums\EntitlementKey;
use App\Filament\Clusters\Website\Pages\WebsiteOverview;
use App\Filament\Pages\Campaigns;
use App\Filament\Pages\CampaignWorkspace;
use App\Filament\Pages\CommunicationCalendar;
use App\Filament\Pages\DesignStudio;
use App\Filament\Pages\FaithFlow;
use App\Filament\Resources\ContentItemResource;
use App\Models\Campaign;
use App\Models\CampaignCommunication;
use App\Models\Church;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;

final class ChurchCommunicationsSnapshotBuilder
{
    /** @var array<string, bool> */
    private array $entitlements = [];

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CommunicationCalendarQuery $calendar,
        private readonly CommunicationAttentionQuery $attention,
        private readonly EntitlementResolver $entitlementResolver,
    ) {}

    public function build(): ChurchCommunicationsSnapshot
    {
        $membership = $this->tenant->currentMembership();
        $church = $this->tenant->currentChurch();
        if ($membership === null || $church === null) {
            throw new DomainException('A trusted Church workspace is required to build Communications.');
        }

        $capabilities = collect($membership->capabilities());
        $has = fn (Capability $capability): bool => $capabilities->contains($capability);
        if (! self::hasRelevantCapability($membership->capabilities())) {
            throw new DomainException('The current membership cannot access Communications.');
        }

        $timezone = $church->timezone ?: 'UTC';
        $now = CarbonImmutable::now($timezone);
        $weekStart = $now->startOfWeek();
        $weekEnd = $now->endOfWeek();
        $canViewCampaigns = $has(Capability::CampaignsView);
        $canManageContent = $has(Capability::ContentManage);
        $canUseFaithFlow = $has(Capability::FaithflowUse) && $this->entitled($church, EntitlementKey::FaithFlowEnabled);
        $canViewDesigns = $has(Capability::DesignsView) && $this->entitled($church, EntitlementKey::DesignEnabled);
        $canManageDesigns = $has(Capability::DesignsManage) && $canViewDesigns;
        $canUseWebsite = $has(Capability::WebsiteContentView) && $this->entitled($church, EntitlementKey::WebsiteEnabled);

        $entries = $canViewCampaigns
            ? $this->calendar->between($church, $weekStart, $weekEnd, $timezone, canManageContent: $canManageContent, canUseFaithFlow: $canUseFaithFlow, canViewDesigns: $canViewDesigns, canManageDesigns: $canManageDesigns, canUseWebsite: $canUseWebsite)
            : [];
        $unplaced = $canViewCampaigns
            ? $this->calendar->unplaced($church, $timezone, canManageContent: $canManageContent, canUseFaithFlow: $canUseFaithFlow, canViewDesigns: $canViewDesigns, canManageDesigns: $canManageDesigns, canUseWebsite: $canUseWebsite, limit: 6)
            : [];

        $actions = [];
        if ($canManageContent) {
            array_push($actions, ...$this->attention->content($church));
        }
        if ($canViewCampaigns) {
            array_push($actions, ...$this->attention->approaching($church, $now->utc(), $now->addDays(7)->utc()));
        }

        $week = collect($entries)->groupBy(fn (CommunicationCalendarEntry $entry): string => $entry->dateKey())->all();
        $today = collect($entries)->filter(fn (CommunicationCalendarEntry $entry): bool => $entry->dateKey() === $now->toDateString())->values()->all();
        $weekCounts = $this->counts($entries);

        return new ChurchCommunicationsSnapshot(
            $church->name,
            $timezone,
            $actions,
            $today,
            $week,
            $unplaced,
            $this->campaigns($church, $canViewCampaigns),
            $this->destinations($church, $has),
            $weekCounts,
            $has(Capability::CampaignsManage),
        );
    }

    /** @param list<Capability> $capabilities */
    public static function hasRelevantCapability(array $capabilities): bool
    {
        $relevant = [
            Capability::ContentView,
            Capability::CampaignsView,
            Capability::DesignsView,
            Capability::MediaView,
            Capability::WebsiteContentView,
            Capability::FaithflowUse,
        ];

        return collect($capabilities)->contains(fn (Capability $capability): bool => in_array($capability, $relevant, true));
    }

    /** @param list<CommunicationCalendarEntry> $entries */
    private function counts(array $entries): array
    {
        $counts = collect($entries)->countBy('preparationKey');

        return [
            'total' => count($entries),
            'prepared' => (int) $counts->get(CampaignCommunication::READINESS_PREPARED, 0),
            'awaiting_approval' => (int) $counts->get(CampaignCommunication::READINESS_AWAITING_APPROVAL, 0),
            'in_preparation' => (int) $counts->get(CampaignCommunication::READINESS_IN_PREPARATION, 0),
            'not_started' => (int) $counts->get(CampaignCommunication::READINESS_NOT_STARTED, 0),
            'outstanding' => (int) $counts->get(CampaignCommunication::READINESS_OUTSTANDING, 0),
        ];
    }

    /** @return list<CampaignCommunicationSummary> */
    private function campaigns(Church $church, bool $canView): array
    {
        if (! $canView) {
            return [];
        }

        return $church->campaigns()
            ->whereIn('status', [CampaignStatus::ACTIVE->value, CampaignStatus::PLANNED->value])
            ->with('communications.contentItem')
            ->orderByRaw('starts_on is null')
            ->orderBy('starts_on')
            ->limit(4)
            ->get()
            ->map(function (Campaign $campaign): CampaignCommunicationSummary {
                $active = $campaign->communications->whereNull('cancelled_at');
                $preparation = $active->countBy(fn (CampaignCommunication $item): string => $item->readiness())->all();

                return new CampaignCommunicationSummary(
                    $campaign->id,
                    $campaign->title,
                    $campaign->status->label(),
                    $this->campaignPeriod($campaign),
                    $active->count(),
                    $preparation,
                    CampaignWorkspace::getUrl(['campaign' => $campaign]),
                );
            })
            ->all();
    }

    /** @param callable(Capability): bool $has */
    private function destinations(Church $church, callable $has): array
    {
        $destinations = [];
        if ($has(Capability::CampaignsView)) {
            $destinations[] = ['label' => 'Campaigns', 'description' => 'Coordinate a communication objective.', 'destination' => Campaigns::getUrl()];
            $destinations[] = ['label' => 'Calendar', 'description' => 'See planned communications by target date.', 'destination' => CommunicationCalendar::getUrl()];
        }
        if ($has(Capability::ContentView)) {
            $destinations[] = ['label' => 'Content', 'description' => 'Write, review, and approve Church copy.', 'destination' => ContentItemResource::getUrl('index')];
        }
        if ($has(Capability::DesignsView) && $this->entitled($church, EntitlementKey::DesignEnabled)) {
            $destinations[] = ['label' => 'Designs', 'description' => 'Continue visual communication work.', 'destination' => DesignStudio::getUrl()];
        }
        if ($has(Capability::FaithflowUse) && $this->entitled($church, EntitlementKey::FaithFlowEnabled)) {
            $destinations[] = ['label' => 'FaithFlow', 'description' => 'Develop communication ideas from approved sources.', 'destination' => FaithFlow::getUrl()];
        }
        if ($has(Capability::WebsiteContentView) && $this->entitled($church, EntitlementKey::WebsiteEnabled)) {
            $destinations[] = ['label' => 'Website', 'description' => 'Continue Website-owned content work.', 'destination' => WebsiteOverview::getUrl()];
        }

        return $destinations;
    }

    private function entitled(Church $church, EntitlementKey $key): bool
    {
        return $this->entitlements[$key->value] ??= $this->entitlementResolver->allows($church, $key);
    }

    private function campaignPeriod(Campaign $campaign): string
    {
        if ($campaign->starts_on && $campaign->ends_on) {
            return $campaign->starts_on->format('j M').' - '.$campaign->ends_on->format('j M Y');
        }
        if ($campaign->starts_on) {
            return 'Starts '.$campaign->starts_on->format('j M Y');
        }
        if ($campaign->ends_on) {
            return 'Ends '.$campaign->ends_on->format('j M Y');
        }

        return 'Dates not set';
    }
}
