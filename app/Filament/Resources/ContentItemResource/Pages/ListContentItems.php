<?php

namespace App\Filament\Resources\ContentItemResource\Pages;

use App\Campaigns\CampaignCommunicationContext;
use App\Campaigns\CreateCampaignContentItem;
use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Filament\Pages\CampaignWorkspace;
use App\Filament\Resources\ContentItemResource;
use App\Models\CampaignCommunication;
use App\Models\ContentItem;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use LogicException;

class ListContentItems extends ListRecords
{
    protected static string $resource = ContentItemResource::class;

    #[Url(as: 'campaign_communication')]
    public ?int $campaignCommunicationId = null;

    public ?CampaignCommunication $campaignCommunication = null;

    public function mount(): void
    {
        parent::mount();

        if ($this->campaignCommunicationId !== null) {
            try {
                $this->campaignCommunication = app(CampaignCommunicationContext::class)
                    ->forContentCreation($this->campaignCommunicationId);
            } catch (AuthorizationException|LogicException) {
                abort(403);
            }
        }
    }

    public function getTitle(): string
    {
        return 'Content Studio';
    }

    public function getSubheading(): string
    {
        return 'Create, review and prepare your church communications.';
    }

    protected function getHeaderActions(): array
    {
        if ($this->campaignCommunication !== null) {
            return [$this->campaignCreateAction()];
        }

        return [
            ContentItemResource::createContentAction(),
        ];
    }

    private function campaignCreateAction(): CreateAction
    {
        $communication = $this->campaignCommunication;

        return ContentItemResource::createContentAction()
            ->label('Create content')
            ->modalHeading('Create content for '.$communication->title)
            ->modalDescription($communication->purpose ?: 'Create the canonical Content Studio draft for this planned communication.')
            ->fillForm([
                'title' => $communication->title,
                'content_type' => $this->suggestedContentType($communication)?->value,
            ])
            ->using(fn (array $data): ContentItem => app(CreateCampaignContentItem::class)->handle($communication->id, $data))
            ->successRedirectUrl(CampaignWorkspace::getUrl(['campaign' => $communication->campaign_id]));
    }

    private function suggestedContentType(CampaignCommunication $communication): ?ContentType
    {
        return match ($communication->channel->value) {
            'instagram', 'facebook', 'youtube' => ContentType::SOCIAL_CAPTION,
            'whatsapp' => ContentType::WHATSAPP_STATUS_COPY,
            'website' => ContentType::WEBSITE_COPY,
            default => null,
        };
    }

    /**
     * Identical to HasTabs's own implementation, plus an explicit horizontal-scroll
     * affordance on the tab bar's wrapper. Table-page tabs always bind via
     * ->livewireProperty(), which renders through a Blade path that never checks
     * Tabs::scrollable() — so the framework's built-in overflow-dropdown behaviour
     * (schema-Tabs-only) never applies here. This is the confirmed, minimal fix for
     * status tabs clipping on narrow viewports, without shortening labels or
     * removing workflow states.
     */
    public function getTabsContentComponent(): Component
    {
        $tabs = $this->getCachedTabs();

        return Tabs::make()
            ->key('resourceTabs')
            ->livewireProperty('activeTab')
            ->contained(false)
            ->tabs($tabs)
            ->hidden(empty($tabs))
            ->extraAttributes(['class' => 'overflow-x-auto']);
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(fn (): int => ContentItem::query()->count()),
            'draft' => Tab::make(ContentStatus::DRAFT->label())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ContentStatus::DRAFT))
                ->badge(fn (): int => ContentItem::query()->where('status', ContentStatus::DRAFT)->count()),
            'review' => Tab::make(ContentStatus::REVIEW->label())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ContentStatus::REVIEW))
                ->badge(fn (): int => ContentItem::query()->where('status', ContentStatus::REVIEW)->count()),
            'rejected' => Tab::make(ContentStatus::REJECTED->label())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ContentStatus::REJECTED))
                ->badge(fn (): int => ContentItem::query()->where('status', ContentStatus::REJECTED)->count()),
            'approved' => Tab::make(ContentStatus::APPROVED->label())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ContentStatus::APPROVED))
                ->badge(fn (): int => ContentItem::query()->where('status', ContentStatus::APPROVED)->count()),
        ];
    }
}
