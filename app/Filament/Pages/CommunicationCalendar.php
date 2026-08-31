<?php

namespace App\Filament\Pages;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Communications\CommunicationCalendarEntry;
use App\Communications\CommunicationCalendarQuery;
use App\Enums\Capability;
use App\Enums\CommunicationChannel;
use App\Enums\EntitlementKey;
use App\Models\Campaign;
use App\Models\CampaignCommunication;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class CommunicationCalendar extends Page
{
    protected string $view = 'filament.pages.communication-calendar';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Communications';

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?string $title = 'Content Calendar';

    protected static ?string $slug = 'communications/calendar';

    protected static ?int $navigationSort = 1;

    #[Url]
    public string $period = 'week';

    #[Url]
    public ?string $channel = null;

    #[Url]
    public ?int $campaign = null;

    #[Url]
    public ?string $preparation = null;

    public static function canAccess(): bool
    {
        return app(TenantContext::class)->currentMembership()?->hasCapability(Capability::CampaignsView) ?? false;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['period', 'channel', 'campaign', 'preparation'], true)) {
            $this->resetValidation();
        }
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        $tenant = app(TenantContext::class);
        $church = $tenant->currentChurch();
        $membership = $tenant->currentMembership();
        abort_unless($church && $membership, 403);

        $timezone = $church->timezone ?: 'UTC';
        $now = CarbonImmutable::now($timezone);
        $period = in_array($this->period, ['today', 'week'], true) ? $this->period : 'week';
        $start = $period === 'today' ? $now->startOfDay() : $now->startOfWeek();
        $end = $period === 'today' ? $now->endOfDay() : $now->endOfWeek();
        $preparation = in_array($this->preparation, $this->preparationKeys(), true) ? $this->preparation : null;
        $channel = CommunicationChannel::tryFrom((string) $this->channel)?->value;
        $campaignId = $this->campaign && Campaign::query()->whereKey($this->campaign)->exists() ? $this->campaign : null;
        $query = app(CommunicationCalendarQuery::class);
        $entitlements = app(EntitlementResolver::class);
        $canUseFaithFlow = $membership->hasCapability(Capability::FaithflowUse) && $entitlements->allows($church, EntitlementKey::FaithFlowEnabled);
        $canViewDesigns = $membership->hasCapability(Capability::DesignsView) && $entitlements->allows($church, EntitlementKey::DesignEnabled);
        $canManageDesigns = $membership->hasCapability(Capability::DesignsManage) && $canViewDesigns;
        $canUseWebsite = $membership->hasCapability(Capability::WebsiteContentView) && $entitlements->allows($church, EntitlementKey::WebsiteEnabled);
        $canManageWebsite = $membership->hasCapability(Capability::WebsiteContentManage) && $canUseWebsite;
        $entries = $query->between($church, $start, $end, $timezone, $channel, $campaignId, $preparation, canManageContent: $membership->hasCapability(Capability::ContentManage), canUseFaithFlow: $canUseFaithFlow, canViewDesigns: $canViewDesigns, canManageDesigns: $canManageDesigns, canUseWebsite: $canUseWebsite, canManageWebsite: $canManageWebsite);
        $unplaced = $query->unplaced($church, $timezone, $channel, $campaignId, $preparation, $membership->hasCapability(Capability::ContentManage), $canUseFaithFlow, $canViewDesigns, $canManageDesigns, $canUseWebsite, $canManageWebsite);

        return [
            'churchName' => $church->name,
            'timezone' => $timezone,
            'period' => $period,
            'periodLabel' => $period === 'today' ? $now->format('l, j F') : $start->format('j M').' - '.$end->format('j M Y'),
            'groups' => collect($entries)->groupBy(fn (CommunicationCalendarEntry $entry): string => $entry->dateKey())->all(),
            'unplaced' => $unplaced,
            'channels' => collect(CommunicationChannel::cases())->mapWithKeys(fn (CommunicationChannel $item): array => [$item->value => $item->label()])->all(),
            'campaigns' => Campaign::query()->orderBy('title')->pluck('title', 'id')->all(),
            'preparations' => collect($this->preparationKeys())->mapWithKeys(fn (string $key): array => [$key => $query->readinessLabel($key)])->all(),
            'canManageCampaigns' => $membership->hasCapability(Capability::CampaignsManage),
        ];
    }

    /** @return list<string> */
    private function preparationKeys(): array
    {
        return [
            CampaignCommunication::READINESS_NOT_STARTED,
            CampaignCommunication::READINESS_IN_PREPARATION,
            CampaignCommunication::READINESS_AWAITING_APPROVAL,
            CampaignCommunication::READINESS_PREPARED,
            CampaignCommunication::READINESS_OUTSTANDING,
        ];
    }
}
