<?php

namespace App\Filament\Pages;

use App\Enums\Capability;
use App\Enums\ChurchOnboardingStatus;
use App\Enums\ChurchOnboardingStep;
use App\Enums\DayOfWeek;
use App\Enums\SocialPlatform;
use App\Filament\Clusters\Website\Pages\EditBrand;
use App\Filament\Clusters\Website\Pages\WebsiteOverview;
use App\Models\Church;
use App\Models\ChurchOnboardingState;
use App\Models\ChurchServiceTime;
use App\Models\ChurchSocialLink;
use App\Onboarding\ChurchOnboardingService;
use App\Support\TenantContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class GuidedChurchSetup extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Church Setup';

    protected static ?string $title = 'Church Setup';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.guided-church-setup';

    public ?int $stateId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $timezone = 'UTC';

    /** @var array<int, array{label: string, day_of_week: string, time: string}> */
    public array $serviceTimes = [];

    /** @var array<int, array{platform: string, url: string}> */
    public array $socialLinks = [];

    public static function canAccess(): bool
    {
        return app(TenantContext::class)->currentMembership()?->hasCapability(Capability::ChurchIdentityManage) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $state = ChurchOnboardingState::query()->first();

        return $state?->status === ChurchOnboardingStatus::IN_PROGRESS ? 'Continue' : null;
    }

    public function mount(): void
    {
        Gate::authorize('update', $this->church());
        $state = ChurchOnboardingState::query()->first();
        $this->stateId = $state?->id;
        $this->fillIdentity();
        $this->serviceTimes = [['label' => '', 'day_of_week' => DayOfWeek::SUNDAY->value, 'time' => '']];
        $this->socialLinks = [['platform' => SocialPlatform::INSTAGRAM->value, 'url' => '']];
    }

    public function start(ChurchOnboardingService $service): void
    {
        $state = $service->start($this->church(), auth()->user());
        $this->stateId = $state->id;
    }

    public function resume(ChurchOnboardingService $service): void
    {
        $this->start($service);
    }

    public function saveIdentity(ChurchOnboardingService $service): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', 'timezone'],
        ]);
        $church = $this->church();
        Gate::authorize('update', $church);
        $church->update([
            'name' => $data['name'], 'email' => $data['email'] ?: null, 'phone' => $data['phone'] ?: null,
            'address' => $data['address'] ?: null, 'timezone' => $data['timezone'],
        ]);
        $this->advance($service, ChurchOnboardingStep::BRAND);
        Notification::make()->title('Church essentials saved')->success()->send();
    }

    public function addServiceTime(): void
    {
        $this->serviceTimes[] = ['label' => '', 'day_of_week' => DayOfWeek::SUNDAY->value, 'time' => ''];
    }

    public function removeServiceTime(int $index): void
    {
        unset($this->serviceTimes[$index]);
        $this->serviceTimes = array_values($this->serviceTimes);
    }

    public function saveServiceTimes(ChurchOnboardingService $service): void
    {
        $rows = collect($this->serviceTimes)->filter(fn (array $row) => filled($row['label']) || filled($row['time']));
        if ($rows->isNotEmpty()) {
            $this->serviceTimes = $rows->values()->all();
            $this->validate([
                'serviceTimes.*.label' => ['required', 'string', 'max:255'],
                'serviceTimes.*.day_of_week' => ['required', 'in:'.implode(',', array_column(DayOfWeek::cases(), 'value'))],
                'serviceTimes.*.time' => ['required', 'string', 'max:255'],
            ]);
            Gate::authorize('create', ChurchServiceTime::class);
            foreach ($this->serviceTimes as $index => $row) {
                ChurchServiceTime::query()->firstOrCreate(
                    ['label' => $row['label'], 'day_of_week' => $row['day_of_week'], 'time' => $row['time']],
                    ['sort_order' => ChurchServiceTime::query()->max('sort_order') + $index + 1],
                );
            }
        }
        $this->advance($service, ChurchOnboardingStep::DIGITAL_PRESENCE);
        Notification::make()->title($rows->isEmpty() ? 'Service times skipped' : 'Service times saved')->success()->send();
    }

    public function addSocialLink(): void
    {
        $this->socialLinks[] = ['platform' => SocialPlatform::INSTAGRAM->value, 'url' => ''];
    }

    public function removeSocialLink(int $index): void
    {
        unset($this->socialLinks[$index]);
        $this->socialLinks = array_values($this->socialLinks);
    }

    public function saveDigitalPresence(ChurchOnboardingService $service): void
    {
        $rows = collect($this->socialLinks)->filter(fn (array $row) => filled($row['url']));
        if ($rows->isNotEmpty()) {
            $this->socialLinks = $rows->values()->all();
            $this->validate([
                'socialLinks.*.platform' => ['required', 'in:'.implode(',', array_column(SocialPlatform::cases(), 'value'))],
                'socialLinks.*.url' => ['required', 'url', 'max:255'],
            ]);
            Gate::authorize('create', ChurchSocialLink::class);
            foreach ($this->socialLinks as $index => $row) {
                ChurchSocialLink::query()->firstOrCreate(
                    ['platform' => $row['platform'], 'url' => $row['url']],
                    ['sort_order' => ChurchSocialLink::query()->max('sort_order') + $index + 1],
                );
            }
        }
        $this->advance($service, ChurchOnboardingStep::COMPLETE);
        Notification::make()->title($rows->isEmpty() ? 'Digital presence skipped' : 'Digital presence saved')->success()->send();
    }

    public function skip(ChurchOnboardingService $service): void
    {
        $this->advance($service, $this->state()->current_step->next());
    }

    public function finish(ChurchOnboardingService $service): void
    {
        $service->complete($this->state(), auth()->user());
        $this->redirect(filament()->getHomeUrl());
    }

    public function dismiss(ChurchOnboardingService $service): void
    {
        $service->dismiss($this->state(), auth()->user());
        $this->redirect(filament()->getHomeUrl());
    }

    public function exitSetup(): void
    {
        $this->redirect(filament()->getHomeUrl());
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        $state = $this->stateId ? ChurchOnboardingState::query()->find($this->stateId) : null;
        $subscription = $this->church()->currentSubscription;

        return [
            'state' => $state,
            'step' => $state?->current_step ?? ChurchOnboardingStep::WELCOME,
            'statuses' => ChurchOnboardingStatus::class,
            'steps' => ChurchOnboardingStep::cases(),
            'dayOptions' => DayOfWeek::options(),
            'socialOptions' => SocialPlatform::options(),
            'trialEndsAt' => $subscription?->trial_ends_at,
            'trialDaysRemaining' => $subscription?->trial_ends_at?->isFuture() ? max(1, (int) now()->ceilDay()->diffInDays($subscription->trial_ends_at->ceilDay())) : 0,
            'brandConfigured' => $this->church()->brandProfile()->where(fn ($query) => $query->whereNotNull('primary_logo_media_id')->orWhereNotNull('primary_color'))->exists(),
            'brandUrl' => EditBrand::getUrl(),
            'websiteUrl' => WebsiteOverview::getUrl(),
            'serviceTimeCount' => $this->church()->serviceTimes()->count(),
            'socialLinkCount' => $this->church()->socialLinks()->count(),
        ];
    }

    private function church(): Church
    {
        return app(TenantContext::class)->currentChurch();
    }

    private function state(): ChurchOnboardingState
    {
        return ChurchOnboardingState::query()->findOrFail($this->stateId);
    }

    private function advance(ChurchOnboardingService $service, ChurchOnboardingStep $next): void
    {
        $state = $service->advance($this->state(), $next, auth()->user());
        $this->stateId = $state->id;
    }

    private function fillIdentity(): void
    {
        $church = $this->church();
        $this->name = $church->name;
        $this->email = $church->email ?? '';
        $this->phone = $church->phone ?? '';
        $this->address = $church->address ?? '';
        $this->timezone = $church->timezone;
    }
}
