<?php

namespace App\Filament\Pages;

use App\Campaigns\CampaignManager;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignCommunication;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class Campaigns extends Page
{
    protected string $view = 'filament.pages.campaigns';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Campaigns';

    protected static ?string $title = 'Campaigns';

    protected static ?string $slug = 'campaigns';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', Campaign::class) ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [$this->createCampaignAction()];
    }

    public function createCampaignAction(): Action
    {
        return Action::make('createCampaign')
            ->label('Create Campaign')
            ->icon('heroicon-o-plus')
            ->modalHeading('Create a Campaign')
            ->modalDescription('Start with the initiative and its timeframe. You can build the communication plan next.')
            ->schema($this->campaignSchema())
            ->action(function (array $data): void {
                $campaign = app(CampaignManager::class)->create($data);

                $this->redirect(CampaignWorkspace::getUrl(['campaign' => $campaign->id]), navigate: true);
            });
    }

    /** @return array<Component> */
    private function campaignSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Campaign name')
                ->placeholder('Easter 2027')
                ->required()
                ->maxLength(255),
            Textarea::make('purpose')
                ->label('Purpose')
                ->helperText('What does your church need to communicate through this initiative?')
                ->rows(4)
                ->maxLength(2000),
            DatePicker::make('starts_on')
                ->label('Start date')
                ->native(false),
            DatePicker::make('ends_on')
                ->label('End date')
                ->native(false)
                ->rule('after_or_equal:starts_on'),
        ];
    }

    /** @return array<string, Collection<int, Campaign>> */
    public function campaignSections(): array
    {
        $campaigns = Campaign::query()
            ->with(['communications.contentItem'])
            ->orderByRaw('starts_on is null')
            ->orderBy('starts_on')
            ->orderBy('title')
            ->get();

        return [
            'Active' => $campaigns->where('status', CampaignStatus::ACTIVE),
            'Planned' => $campaigns->where('status', CampaignStatus::PLANNED),
            'Drafts' => $campaigns->where('status', CampaignStatus::DRAFT),
            'Completed' => $campaigns->where('status', CampaignStatus::COMPLETED),
            'Archived' => $campaigns->where('status', CampaignStatus::ARCHIVED),
        ];
    }

    /** @return array{total: int, prepared: int, awaiting_approval: int, in_preparation: int, not_started: int, outstanding: int} */
    public function readinessCounts(Campaign $campaign): array
    {
        $active = $campaign->communications->whereNull('cancelled_at');
        $counts = $active->countBy(fn ($communication): string => $communication->readiness());

        return [
            'total' => $active->count(),
            'prepared' => $counts->get('prepared', 0),
            'awaiting_approval' => $counts->get('awaiting_approval', 0),
            'in_preparation' => $counts->get('in_preparation', 0),
            'not_started' => $counts->get('not_started', 0),
            'outstanding' => $counts->get('outstanding', 0),
        ];
    }

    public function nearestTarget(Campaign $campaign): ?CampaignCommunication
    {
        return $campaign->communications
            ->whereNull('cancelled_at')
            ->whereNotNull('target_at')
            ->filter(fn ($communication): bool => $communication->target_at->isFuture())
            ->sortBy('target_at')
            ->first();
    }

    public function statusColor(CampaignStatus $status): string
    {
        return match ($status) {
            CampaignStatus::ACTIVE => 'success',
            CampaignStatus::PLANNED => 'info',
            CampaignStatus::DRAFT => 'gray',
            CampaignStatus::COMPLETED => 'primary',
            CampaignStatus::ARCHIVED => 'gray',
        };
    }

    public function periodLabel(Campaign $campaign): string
    {
        if ($campaign->starts_on && $campaign->ends_on) {
            return $campaign->starts_on->isSameYear($campaign->ends_on)
                ? $campaign->starts_on->format('j M').' – '.$campaign->ends_on->format('j M Y')
                : $campaign->starts_on->format('j M Y').' – '.$campaign->ends_on->format('j M Y');
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
