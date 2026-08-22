<?php

namespace App\Filament\Pages;

use App\Campaigns\CampaignCommunicationContext;
use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Campaigns\CampaignMediaManager;
use App\Enums\CampaignStatus;
use App\Enums\Capability;
use App\Enums\CommunicationChannel;
use App\Filament\Clusters\Website\Pages\WebsiteOverview;
use App\Filament\Resources\ContentItemResource;
use App\Models\Campaign;
use App\Models\CampaignCommunication;
use App\Models\CampaignMedia;
use App\Models\ContentItem;
use App\Models\MediaAsset;
use App\PublicWebsite\PublicMedia;
use App\PublicWebsite\WebsitePublicationStatus;
use App\Support\TenantContext;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

class CampaignWorkspace extends Page
{
    protected string $view = 'filament.pages.campaign-workspace';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'campaigns/{campaign}';

    #[Locked]
    public int $campaignId;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', Campaign::class) ?? false;
    }

    public function mount(int|string $campaign): void
    {
        $record = Campaign::query()->findOrFail($campaign);
        Gate::authorize('view', $record);
        $this->campaignId = $record->id;
    }

    public function hydrate(): void
    {
        Gate::authorize('view', $this->campaign());
    }

    public function getTitle(): string
    {
        return $this->campaign()->title;
    }

    public function getSubheading(): ?string
    {
        return $this->campaign()->purpose;
    }

    public function campaign(): Campaign
    {
        return Campaign::query()
            ->with(['communications.contentItem', 'mediaAssociations.mediaAsset'])
            ->findOrFail($this->campaignId);
    }

    protected function getHeaderActions(): array
    {
        $campaign = $this->campaign();

        return [
            $this->editCampaignAction(),
            Action::make('planCampaign')
                ->label('Plan Campaign')
                ->icon('heroicon-o-calendar-days')
                ->visible($campaign->status === CampaignStatus::DRAFT)
                ->authorize(fn (): bool => Gate::allows('update', $campaign))
                ->action(fn () => $this->transition(CampaignStatus::PLANNED, 'Campaign planned.')),
            Action::make('returnToDraft')
                ->label('Return to Draft')
                ->color('gray')
                ->visible($campaign->status === CampaignStatus::PLANNED)
                ->authorize(fn (): bool => Gate::allows('update', $campaign))
                ->action(fn () => $this->transition(CampaignStatus::DRAFT, 'Campaign returned to Draft.')),
            Action::make('activateCampaign')
                ->label('Activate Campaign')
                ->icon('heroicon-o-play')
                ->visible($campaign->status === CampaignStatus::PLANNED)
                ->authorize(fn (): bool => Gate::allows('update', $campaign))
                ->action(fn () => $this->transition(CampaignStatus::ACTIVE, 'Campaign is active.')),
            Action::make('returnToPlanned')
                ->label('Return to Planned')
                ->color('gray')
                ->visible($campaign->status === CampaignStatus::ACTIVE)
                ->authorize(fn (): bool => Gate::allows('update', $campaign))
                ->requiresConfirmation()
                ->modalHeading('Return this Campaign to Planned?')
                ->modalDescription('It will no longer be considered actively in progress. Your communication plan and linked content will stay unchanged.')
                ->modalSubmitActionLabel('Return to Planned')
                ->action(fn () => $this->transition(CampaignStatus::PLANNED, 'Campaign returned to Planned.', confirmed: true)),
            Action::make('completeCampaign')
                ->label('Complete Campaign')
                ->icon('heroicon-o-check-circle')
                ->visible($campaign->status === CampaignStatus::ACTIVE)
                ->authorize(fn (): bool => Gate::allows('update', $campaign))
                ->requiresConfirmation()
                ->modalDescription('Mark this communication initiative as complete? You can archive it afterwards.')
                ->action(fn () => $this->transition(CampaignStatus::COMPLETED, 'Campaign completed.')),
            Action::make('archiveCampaign')
                ->label('Archive Campaign')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->visible($campaign->status === CampaignStatus::COMPLETED)
                ->authorize(fn (): bool => Gate::allows('update', $campaign))
                ->requiresConfirmation()
                ->modalDescription('Archive this completed Campaign? It will move out of normal planning views.')
                ->action(fn () => $this->transition(CampaignStatus::ARCHIVED, 'Campaign archived.')),
            Action::make('deleteCampaign')
                ->label('Delete Campaign')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible($campaign->status === CampaignStatus::DRAFT)
                ->authorize(fn (): bool => Gate::allows('delete', $campaign))
                ->requiresConfirmation()
                ->modalHeading('Delete this Draft Campaign?')
                ->modalDescription('The Campaign and its communication plan will be removed. Linked Content Studio content will not be deleted.')
                ->action(function () use ($campaign): void {
                    app(CampaignManager::class)->delete($campaign);
                    Notification::make()->success()->title('Draft Campaign deleted.')->send();
                    $this->redirect(Campaigns::getUrl(), navigate: true);
                }),
        ];
    }

    public function editCampaignAction(): Action
    {
        return Action::make('editCampaign')
            ->label('Edit details')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->authorize(fn (): bool => Gate::allows('update', $this->campaign()))
            ->fillForm(fn (): array => $this->campaign()->only(['title', 'purpose', 'starts_on', 'ends_on']))
            ->schema($this->campaignSchema())
            ->action(function (array $data): void {
                app(CampaignManager::class)->update($this->campaign(), $data);
                Notification::make()->success()->title('Campaign details updated.')->send();
            });
    }

    public function addCommunicationAction(): Action
    {
        return Action::make('addCommunication')
            ->label('Add communication')
            ->icon('heroicon-o-plus')
            ->modalHeading('Add to the communication plan')
            ->modalDescription('Describe the communication your church needs to prepare. Target dates are planning guidance, not scheduled publishing.')
            ->authorize(fn (): bool => Gate::allows('update', $this->campaign()))
            ->schema($this->communicationSchema())
            ->action(function (array $data): void {
                $contentItemId = $data['content_item_id'] ?? null;
                unset($data['content_item_id']);

                $data['sort_order'] = ($this->campaign()->communications->max('sort_order') ?? -1) + 1;
                $communication = app(CampaignCommunicationManager::class)->add($this->campaign(), $data);

                if ($contentItemId !== null) {
                    app(CampaignCommunicationManager::class)->linkContentItem($communication, ContentItem::query()->findOrFail($contentItemId));
                }

                Notification::make()->success()->title('Communication added to the plan.')->send();
            });
    }

    public function editCommunicationAction(): Action
    {
        return Action::make('editCommunication')
            ->label('Edit communication')
            ->authorize(fn (array $arguments): bool => Gate::allows('update', $this->communication($arguments)))
            ->fillForm(function (array $arguments): array {
                $communication = $this->communication($arguments);

                return $communication->only(['title', 'purpose', 'channel', 'target_at', 'content_item_id']);
            })
            ->schema($this->communicationSchema())
            ->action(function (array $data, array $arguments): void {
                $communication = $this->communication($arguments);
                $contentItemId = $data['content_item_id'] ?? null;
                unset($data['content_item_id']);

                $manager = app(CampaignCommunicationManager::class);
                $manager->update($communication, $data);

                if ($contentItemId === null) {
                    $manager->unlinkContentItem($communication);
                } else {
                    $manager->linkContentItem($communication, ContentItem::query()->findOrFail($contentItemId));
                }

                Notification::make()->success()->title('Communication updated.')->send();
            });
    }

    public function unlinkCommunicationContentAction(): Action
    {
        return Action::make('unlinkCommunicationContent')
            ->label('Unlink content')
            ->color('gray')
            ->authorize(fn (array $arguments): bool => Gate::allows('update', $this->communication($arguments)))
            ->requiresConfirmation()
            ->modalHeading('Unlink this Content Studio item?')
            ->modalDescription('Only the association will be removed. The Content Studio item will remain unchanged.')
            ->action(function (array $arguments): void {
                app(CampaignCommunicationManager::class)->unlinkContentItem($this->communication($arguments));
                Notification::make()->success()->title('Content unlinked.')->send();
            });
    }

    public function linkExistingContentAction(): Action
    {
        return Action::make('linkExistingContent')
            ->label('Link existing content')
            ->authorize(fn (array $arguments): bool => Gate::allows('update', $this->communication($arguments))
                && Gate::allows('viewAny', ContentItem::class))
            ->schema([
                Select::make('content_item_id')
                    ->label('Content Studio item')
                    ->options(fn (): array => ContentItem::query()->orderBy('title')->get()->mapWithKeys(
                        fn (ContentItem $item): array => [$item->id => "{$item->title} — {$item->status->label()}"]
                    )->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                app(CampaignCommunicationManager::class)->linkContentItem(
                    $this->communication($arguments),
                    ContentItem::query()->findOrFail($data['content_item_id']),
                );

                Notification::make()->success()->title('Existing content linked.')->send();
            });
    }

    public function cancelCommunicationAction(): Action
    {
        return Action::make('cancelCommunication')
            ->label('Cancel communication')
            ->color('danger')
            ->authorize(fn (array $arguments): bool => Gate::allows('update', $this->communication($arguments)))
            ->requiresConfirmation()
            ->modalDescription('This removes the communication from active readiness. Linked Content Studio content will not be deleted.')
            ->action(function (array $arguments): void {
                app(CampaignCommunicationManager::class)->cancel($this->communication($arguments));
                Notification::make()->success()->title('Communication cancelled.')->send();
            });
    }

    public function restoreCommunicationAction(): Action
    {
        return Action::make('restoreCommunication')
            ->label('Restore to plan')
            ->authorize(fn (array $arguments): bool => Gate::allows('update', $this->communication($arguments)))
            ->action(function (array $arguments): void {
                app(CampaignCommunicationManager::class)->restore($this->communication($arguments));
                Notification::make()->success()->title('Communication restored.')->send();
            });
    }

    public function attachMediaAction(): Action
    {
        return Action::make('attachMedia')
            ->label('Attach existing media')
            ->icon('heroicon-o-photo')
            ->authorize(fn (): bool => Gate::allows('update', $this->campaign())
                && Gate::allows('viewAny', MediaAsset::class))
            ->schema([
                Select::make('media_asset_id')
                    ->label('Institutional media')
                    ->options(fn (): array => MediaAsset::query()
                        ->orderBy('original_filename')
                        ->pluck('original_filename', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
                TextInput::make('label')
                    ->label('Campaign label')
                    ->placeholder('Easter hero artwork')
                    ->helperText('Optional context for this Campaign. The institutional asset itself stays unchanged.')
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                app(CampaignMediaManager::class)->attach(
                    $this->campaign(),
                    MediaAsset::query()->findOrFail($data['media_asset_id']),
                    $data['label'] ?? null,
                );

                Notification::make()->success()->title('Media attached to Campaign.')->send();
            });
    }

    public function editMediaAssociationAction(): Action
    {
        return Action::make('editMediaAssociation')
            ->label('Edit label')
            ->authorize(fn (array $arguments): bool => Gate::allows('update', $this->campaign())
                && Gate::allows('viewAny', MediaAsset::class)
                && $this->mediaAssociation($arguments)->mediaAsset !== null)
            ->fillForm(fn (array $arguments): array => [
                'label' => $this->mediaAssociation($arguments)->label,
            ])
            ->schema([
                TextInput::make('label')
                    ->label('Campaign label')
                    ->maxLength(255),
            ])
            ->action(function (array $data, array $arguments): void {
                app(CampaignMediaManager::class)->update(
                    $this->mediaAssociation($arguments),
                    $data['label'] ?? null,
                );
                Notification::make()->success()->title('Campaign media label updated.')->send();
            });
    }

    public function detachMediaAction(): Action
    {
        return Action::make('detachMedia')
            ->label('Remove from Campaign')
            ->color('danger')
            ->authorize(fn (array $arguments): bool => Gate::allows('update', $this->campaign())
                && Gate::allows('viewAny', MediaAsset::class))
            ->requiresConfirmation()
            ->modalHeading('Remove this media from the Campaign?')
            ->modalDescription('Only the Campaign association will be removed. The institutional MediaAsset will remain available elsewhere in Keryon.')
            ->action(function (array $arguments): void {
                app(CampaignMediaManager::class)->detach($this->mediaAssociation($arguments));
                Notification::make()->success()->title('Media removed from Campaign.')->send();
            });
    }

    public function moveCommunication(int $communicationId, string $direction): void
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $campaign = $this->campaign();
        Gate::authorize('update', $campaign);
        $communications = $campaign->communications->whereNull('cancelled_at')->values();
        $index = $communications->search(fn (CampaignCommunication $item): bool => $item->id === $communicationId);
        abort_if($index === false, 404);

        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if (! $communications->has($swapIndex)) {
            return;
        }

        $current = $communications[$index];
        $swap = $communications[$swapIndex];

        DB::transaction(function () use ($current, $swap, $index, $swapIndex): void {
            $manager = app(CampaignCommunicationManager::class);
            $manager->update($current, ['sort_order' => $swapIndex]);
            $manager->update($swap, ['sort_order' => $index]);
        });
    }

    /** @return array{total: int, prepared: int, awaiting_approval: int, in_preparation: int, not_started: int, outstanding: int} */
    public function readinessCounts(): array
    {
        $active = $this->campaign()->communications->whereNull('cancelled_at');
        $counts = $active->countBy(fn (CampaignCommunication $communication): string => $communication->readiness());

        return [
            'total' => $active->count(),
            'prepared' => $counts->get('prepared', 0),
            'awaiting_approval' => $counts->get('awaiting_approval', 0),
            'in_preparation' => $counts->get('in_preparation', 0),
            'not_started' => $counts->get('not_started', 0),
            'outstanding' => $counts->get('outstanding', 0),
        ];
    }

    /** @return Collection<int, CampaignCommunication> */
    public function upcomingTargets(): Collection
    {
        return $this->campaign()->communications
            ->whereNull('cancelled_at')
            ->whereNotNull('target_at')
            ->filter(fn (CampaignCommunication $communication): bool => $communication->target_at->isFuture())
            ->sortBy('target_at')
            ->take(3)
            ->values();
    }

    public function readinessLabel(CampaignCommunication $communication): string
    {
        if ($communication->channel === CommunicationChannel::WEBSITE && $communication->readiness() === CampaignCommunication::READINESS_PREPARED) {
            return 'Content ready';
        }

        return match ($communication->readiness()) {
            CampaignCommunication::READINESS_PREPARED => 'Ready',
            CampaignCommunication::READINESS_AWAITING_APPROVAL => 'Awaiting approval',
            CampaignCommunication::READINESS_IN_PREPARATION => $communication->contentItem?->status?->value === 'rejected' ? 'Needs work' : 'In preparation',
            CampaignCommunication::READINESS_OUTSTANDING => 'Content unavailable',
            CampaignCommunication::READINESS_CANCELLED => 'Cancelled',
            default => 'Not started',
        };
    }

    public function readinessColor(CampaignCommunication $communication): string
    {
        return match ($communication->readiness()) {
            CampaignCommunication::READINESS_PREPARED => 'success',
            CampaignCommunication::READINESS_AWAITING_APPROVAL => 'warning',
            CampaignCommunication::READINESS_IN_PREPARATION => 'info',
            CampaignCommunication::READINESS_OUTSTANDING => 'danger',
            default => 'gray',
        };
    }

    public function contentItemUrl(CampaignCommunication $communication): ?string
    {
        return $communication->contentItem && ! $communication->contentItem->trashed()
            ? ContentItemResource::getUrl('view', ['record' => $communication->contentItem])
            : null;
    }

    public function canCreateContextualContent(CampaignCommunication $communication): bool
    {
        try {
            app(CampaignCommunicationContext::class)->forContentCreation($communication->id);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function canCreateContextualFaithFlow(CampaignCommunication $communication): bool
    {
        try {
            app(CampaignCommunicationContext::class)->forFaithFlow($communication->id);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function canLinkExistingContent(): bool
    {
        return Gate::allows('viewAny', ContentItem::class);
    }

    public function createContentUrl(CampaignCommunication $communication): string
    {
        return ContentItemResource::getUrl('index', [
            'campaign_communication' => $communication->id,
            'action' => 'create',
        ]);
    }

    public function createWithFaithFlowUrl(CampaignCommunication $communication): string
    {
        return FaithFlow::getUrl(['campaign_communication' => $communication->id]);
    }

    public function canViewCampaignMedia(): bool
    {
        return Gate::allows('viewAny', MediaAsset::class);
    }

    public function canManageCampaignMedia(): bool
    {
        return Gate::allows('update', $this->campaign())
            && Gate::allows('viewAny', MediaAsset::class);
    }

    /** @return array{url: string, alt: string, width: int|null, height: int|null}|null */
    public function campaignMediaPreview(CampaignMedia $association): ?array
    {
        return app(PublicMedia::class)->image(
            $association->church_id,
            $association->media_asset_id,
            $association->mediaAsset?->alt_text,
        );
    }

    public function canViewWebsiteCoordination(): bool
    {
        return app(TenantContext::class)
            ->currentMembership()?->hasCapability(Capability::WebsiteContentView) ?? false;
    }

    /** @return Collection<int, CampaignCommunication> */
    public function websiteCommunications(): Collection
    {
        return $this->campaign()->communications
            ->whereNull('cancelled_at')
            ->where('channel', CommunicationChannel::WEBSITE)
            ->values();
    }

    /** @return array{state: string, current: mixed, latest: mixed, pending: bool}|null */
    public function websiteOperationalStatus(): ?array
    {
        return $this->canViewWebsiteCoordination()
            ? app(WebsitePublicationStatus::class)->current()
            : null;
    }

    public function websiteOverviewUrl(): string
    {
        return WebsiteOverview::getUrl();
    }

    private function transition(CampaignStatus $status, string $message, bool $confirmed = false): void
    {
        app(CampaignManager::class)->transition($this->campaign(), $status, $confirmed);
        Notification::make()->success()->title($message)->send();
    }

    private function communication(array $arguments): CampaignCommunication
    {
        return CampaignCommunication::query()
            ->where('campaign_id', $this->campaignId)
            ->findOrFail($arguments['communication'] ?? null);
    }

    private function mediaAssociation(array $arguments): CampaignMedia
    {
        return CampaignMedia::query()
            ->where('campaign_id', $this->campaignId)
            ->findOrFail($arguments['association'] ?? null);
    }

    /** @return array<Component> */
    private function campaignSchema(): array
    {
        return [
            TextInput::make('title')->label('Campaign name')->required()->maxLength(255),
            Textarea::make('purpose')->label('Purpose')->rows(4)->maxLength(2000),
            DatePicker::make('starts_on')->label('Start date')->native(false),
            DatePicker::make('ends_on')->label('End date')->native(false)->rule('after_or_equal:starts_on'),
        ];
    }

    /** @return array<Component> */
    private function communicationSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Communication')
                ->placeholder('Good Friday reminder')
                ->required()
                ->maxLength(255),
            Textarea::make('purpose')
                ->label('Purpose')
                ->helperText('Why does this communication exist, and what should it communicate?')
                ->rows(3)
                ->maxLength(2000),
            Select::make('channel')
                ->label('Channel')
                ->options(collect(CommunicationChannel::cases())->mapWithKeys(fn ($channel): array => [$channel->value => $channel->label()])->all())
                ->helperText('This describes intent only. Keryon will not publish to the selected channel.')
                ->required(),
            DateTimePicker::make('target_at')
                ->label('Target date')
                ->helperText('When this communication should be ready. This does not schedule publishing.')
                ->native(false),
            Select::make('content_item_id')
                ->label('Content Studio item')
                ->placeholder('No content linked yet')
                ->options(fn (): array => ContentItem::query()->orderBy('title')->get()->mapWithKeys(
                    fn (ContentItem $item): array => [$item->id => "{$item->title} — {$item->status->label()}"]
                )->all())
                ->searchable()
                ->helperText('Link existing same-Church content. Campaigns will not copy or edit it.'),
        ];
    }
}
