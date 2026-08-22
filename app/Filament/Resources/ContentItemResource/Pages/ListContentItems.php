<?php

namespace App\Filament\Resources\ContentItemResource\Pages;

use App\Enums\ContentStatus;
use App\Filament\Resources\ContentItemResource;
use App\Models\ContentItem;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListContentItems extends ListRecords
{
    protected static string $resource = ContentItemResource::class;

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
        return [
            ContentItemResource::createContentAction(),
        ];
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
