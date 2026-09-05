<?php

namespace App\Dashboard;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Communications\OrganizationInbox\ChurchOrganizationCommunicationQuery;
use App\Dashboard\Charts\CampaignActivityChartQuery;
use App\Dashboard\Charts\CommunicationsActivityChartQuery;
use App\Dashboard\Charts\CongregationTrendChartQuery;
use App\Dashboard\Charts\FaithFlowUsageChartQuery;
use App\Dashboard\Charts\WebsitePublicationChartQuery;
use App\Enums\Capability;
use App\Enums\ChurchOnboardingStatus;
use App\Enums\EntitlementKey;
use App\Enums\SubscriptionStatus;
use App\Filament\Clusters\Website\Pages\EditBrand;
use App\Filament\Clusters\Website\Pages\EditChurchInformation;
use App\Filament\Clusters\Website\Pages\WebsiteOverview;
use App\Filament\Pages\Campaigns;
use App\Filament\Pages\CareCenterDashboard;
use App\Filament\Pages\ChurchStaffAccess;
use App\Filament\Pages\DesignStudio;
use App\Filament\Pages\FaithFlow;
use App\Filament\Pages\GuidedChurchSetup;
use App\Filament\Pages\OrganizationInbox;
use App\Filament\Resources\CongregationResource;
use App\Filament\Resources\ContentItemResource;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\PublicWebsite\WebsitePublicationStatus;
use App\Support\TenantContext;
use DomainException;

final class ChurchDashboardSnapshotBuilder
{
    /** @var array<string, bool> */
    private array $entitlements = [];

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly DashboardOperationalQuery $queries,
        private readonly EntitlementResolver $entitlementResolver,
        private readonly WebsitePublicationStatus $websiteStatus,
        private readonly CommunicationsActivityChartQuery $communicationsChart,
        private readonly CongregationTrendChartQuery $congregationChart,
        private readonly CampaignActivityChartQuery $campaignChart,
        private readonly WebsitePublicationChartQuery $websiteChart,
        private readonly FaithFlowUsageChartQuery $faithFlowChart,
    ) {}

    public function build(): DashboardSnapshot
    {
        $membership = $this->tenant->currentMembership();
        $church = $this->tenant->currentChurch();

        if ($membership === null || $church === null) {
            throw new DomainException('A trusted Church workspace is required to build the dashboard.');
        }

        $membership->loadMissing('roles');
        $capabilities = $membership->roles
            ->pluck('role')
            ->flatMap(fn ($role) => $role->capabilities())
            ->unique();
        $has = fn (Capability $capability): bool => $capabilities->contains($capability);
        $actions = [];
        $guidance = [];
        $summaries = [];
        $shortcuts = [];
        $charts = [];

        if ($has(Capability::ContentManage)) {
            $content = $this->queries->content();
            if ($content['review'] > 0) {
                $actions[] = new DashboardAction('content.review', 10, $this->countTitle($content['review'], 'Content item is', 'Content items are').' awaiting review', 'Review and approve communication work that is ready for a decision.', ContentItemResource::getUrl('index'), 'Content', $content['review'], 'Review content');
            }
            if ($content['rejected'] > 0) {
                $actions[] = new DashboardAction('content.needs_changes', 20, $this->countTitle($content['rejected'], 'Content item needs', 'Content items need').' changes', 'Revise feedback-ready work before it moves forward.', ContentItemResource::getUrl('index'), 'Content', $content['rejected'], 'Open Content Studio');
            }
        }

        if ($has(Capability::StaffManage)) {
            $pending = $this->queries->pendingStaffInvitations();
            if ($pending > 0) {
                $actions[] = new DashboardAction('staff.pending', 30, "{$pending} staff ".($pending === 1 ? 'invitation is' : 'invitations are').' pending', 'Review invitations that have not yet become active Church memberships.', ChurchStaffAccess::getUrl(), 'Staff', $pending, 'Manage invitations');
            }
        }

        // K-ORG-COMMS-001G §7/§9 — resolved only inside this authorized
        // branch, mirroring the Care query below: a user without
        // OrganizationCommunicationsView never triggers this query at
        // all, not merely "sees no result from it".
        if ($has(Capability::OrganizationCommunicationsView)) {
            $available = app(ChurchOrganizationCommunicationQuery::class)->availableCount();
            if ($available > 0) {
                $actions[] = new DashboardAction(
                    'organization.communications.available',
                    35,
                    'Organization communications waiting',
                    $available === 1
                        ? '1 communication shared with your Church is waiting for a response.'
                        : "{$available} communications shared with your Church are waiting for a response.",
                    OrganizationInbox::getUrl(),
                    'Organization',
                    $available,
                    'View inbox',
                );
            }
        }

        if ($has(Capability::WebsitePublish) && $this->entitled($church, EntitlementKey::WebsiteEnabled)) {
            $publication = $this->websiteStatus->current();
            if ($publication['state'] !== 'published') {
                $actions[] = new DashboardAction('website.unpublished', 40, 'Your church Website is not live', 'Review the working Website and publish it when it is ready.', WebsiteOverview::getUrl(), 'Website', null, 'Open Website');
            } elseif ($publication['pending']) {
                $actions[] = new DashboardAction('website.pending_changes', 40, 'Website changes are ready to publish', 'Your working Website differs from the current public version.', WebsiteOverview::getUrl(), 'Website', null, 'Review changes');
            }
        }

        if ($has(Capability::ChurchIdentityManage)) {
            $state = $church->onboardingState()->first();
            if ($state?->status === ChurchOnboardingStatus::IN_PROGRESS) {
                $actions[] = new DashboardAction('onboarding.continue', 50, 'Continue setting up your church', 'Resume at '.$state->current_step->label().'. Setup remains optional and your workspace is ready to use.', GuidedChurchSetup::getUrl(), 'Setup', null, 'Continue setup');
            }
            if (blank($church->email)) {
                $actions[] = new DashboardAction('identity.email', 60, 'Add a public Church email', 'Give people a dependable contact point for your church.', GuidedChurchSetup::getUrl(), 'Setup', null, 'Add email');
            }
            if (! $church->brandProfile()->where(fn ($query) => $query->whereNotNull('primary_logo_media_id')->orWhereNotNull('primary_color'))->exists()) {
                $guidance[] = new DashboardAction('readiness.brand', 100, 'Add your church brand', 'A logo or brand color helps Website, Design, and Campaign work feel consistent.', EditBrand::getUrl(), 'Optional setup', null, 'Set up brand');
            }
            if (! $church->serviceTimes()->exists()) {
                $guidance[] = new DashboardAction('readiness.service_time', 110, 'Add a service time', 'Make your regular gathering time available to Church communications and Website.', EditChurchInformation::getUrl(), 'Optional setup', null, 'Add service time');
            }
        }

        if ($has(Capability::CampaignsView)) {
            $campaigns = $this->queries->campaigns();
            $summaries['campaigns'] = [
                new DashboardMetric('campaigns.active', 'Active', $campaigns['active']),
                new DashboardMetric('campaigns.approaching', 'Starting in 30 days', $campaigns['approaching']),
            ];
            $shortcuts[] = $this->shortcut('Campaigns', 'Coordinate upcoming Church communications.', Campaigns::getUrl());
        }

        if ($has(Capability::CongregationView)) {
            $congregation = $this->queries->congregation();
            $summaries['congregation'] = [
                new DashboardMetric('congregation.total', 'Total people', $congregation['total']),
                new DashboardMetric('congregation.visitors', 'Visitors', $congregation['visitors']),
                new DashboardMetric('congregation.inactive', 'Inactive', $congregation['inactive']),
            ];
            $shortcuts[] = $this->shortcut('Congregation', 'View the Church directory and member status.', CongregationResource::getUrl('index'));
        }

        // The Care query is deliberately resolved only inside this authorized branch.
        if ($has(Capability::CareView)) {
            $care = app(CareDashboardQuery::class)->attention();
            $summaries['care'] = [
                new DashboardMetric('care.new', 'New requests', $care['new']),
                new DashboardMetric('care.open', 'Open requests', $care['open']),
            ];
            if ($care['new'] > 0) {
                $actions[] = new DashboardAction('care.new', 15, "{$care['new']} new Care ".($care['new'] === 1 ? 'request needs' : 'requests need').' attention', 'Open the Care Center to review the current request queue.', CareCenterDashboard::getUrl(), 'Care', $care['new'], $has(Capability::CareManage) ? 'Review requests' : 'View Care Center');
            }
            $shortcuts[] = $this->shortcut('Care Center', 'Continue confidential Care work.', CareCenterDashboard::getUrl());
        }

        if ($has(Capability::FaithflowUse) && $this->entitled($church, EntitlementKey::FaithFlowEnabled)) {
            $shortcuts[] = $this->shortcut('FaithFlow', 'Shape approved source material into communication ideas.', FaithFlow::getUrl());
        }
        if ($has(Capability::DesignsView) && $this->entitled($church, EntitlementKey::DesignEnabled)) {
            $shortcuts[] = $this->shortcut('Design Studio', 'Create Church-ready visual work.', DesignStudio::getUrl());
        }
        if ($has(Capability::WebsiteContentView) && $this->entitled($church, EntitlementKey::WebsiteEnabled)) {
            $shortcuts[] = $this->shortcut('Website', 'Manage your public Church presence.', WebsiteOverview::getUrl());
        }

        // Capability and entitlement decisions happen before each chart query.
        // The initial dashboard intentionally caps composition at four charts.
        if ($has(Capability::ContentView)) {
            $charts[] = $this->communicationsChart->for($church);
        }
        if ($has(Capability::CongregationManage)) {
            $charts[] = $this->congregationChart->for($church);
        }
        if ($has(Capability::CampaignsView)) {
            $charts[] = $this->campaignChart->for($church);
        }
        if ($has(Capability::WebsiteContentView) && $this->entitled($church, EntitlementKey::WebsiteEnabled)) {
            $charts[] = $this->websiteChart->for($church);
        }
        if (count($charts) < 4 && $has(Capability::FaithflowUse) && $this->entitled($church, EntitlementKey::FaithFlowEnabled)) {
            $charts[] = $this->faithFlowChart->for($church);
        }

        usort($actions, fn (DashboardAction $a, DashboardAction $b): int => $a->priority <=> $b->priority);
        usort($guidance, fn (DashboardAction $a, DashboardAction $b): int => $a->priority <=> $b->priority);

        return new DashboardSnapshot($church->name, $actions, $guidance, $summaries, $shortcuts, $this->trial($church, $membership, $has(Capability::ChurchManage)), $charts);
    }

    private function entitled(Church $church, EntitlementKey $key): bool
    {
        return $this->entitlements[$key->value] ??= $this->entitlementResolver->allows($church, $key);
    }

    /** @return array{label: string, days_remaining: int, ends_at: string}|null */
    private function trial(Church $church, ChurchMembership $membership, bool $canManageChurch): ?array
    {
        if ((! $membership->is_primary && ! $canManageChurch) || $church->current_subscription_id === null) {
            return null;
        }

        $subscription = $church->currentSubscription()->first();
        if ($subscription?->status !== SubscriptionStatus::TRIALING || $subscription->trial_ends_at === null || ! $subscription->trial_ends_at->isFuture()) {
            return null;
        }

        $days = max(1, (int) now()->startOfDay()->diffInDays($subscription->trial_ends_at->startOfDay(), false));

        return ['label' => '21-day trial', 'days_remaining' => $days, 'ends_at' => $subscription->trial_ends_at->toDateString()];
    }

    /** @return array{label: string, description: string, destination: string} */
    private function shortcut(string $label, string $description, string $destination): array
    {
        return compact('label', 'description', 'destination');
    }

    private function countTitle(int $count, string $singular, string $plural): string
    {
        return $count.' '.($count === 1 ? $singular : $plural);
    }
}
