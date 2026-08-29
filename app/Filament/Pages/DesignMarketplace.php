<?php

namespace App\Filament\Pages;

use App\Enums\MarketplacePreviewType;
use App\Marketplace\AcquireMarketplaceItem;
use App\Marketplace\MarketplaceCatalogue;
use App\Marketplace\MarketplacePreviewDelivery;
use App\Marketplace\MarketplaceSourceDelivery;
use App\Models\MarketplaceAcquisition;
use App\Models\MarketplaceItem;
use App\Models\MarketplacePreview;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DesignMarketplace extends Page
{
    protected string $view = 'filament.pages.design-marketplace';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Marketplace';

    protected static string|\UnitEnum|null $navigationGroup = 'Communications';

    protected static ?string $title = 'Design Marketplace';

    protected static ?string $slug = 'design-marketplace';

    protected static ?int $navigationSort = 5;

    #[Url]
    public ?string $product = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', MarketplaceItem::class) ?? false;
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', MarketplaceItem::class);

        if ($this->product !== null) {
            app(MarketplaceCatalogue::class)->findPublishedBySlug($this->product);
        }
    }

    /** @return Collection<int, MarketplaceItem> */
    public function getCatalogueProperty(): Collection
    {
        return app(MarketplaceCatalogue::class)->published();
    }

    public function getSelectedProductProperty(): ?MarketplaceItem
    {
        return $this->product === null ? null : app(MarketplaceCatalogue::class)->findPublishedBySlug($this->product);
    }

    /** @return Collection<int, MarketplaceAcquisition> */
    public function getLibraryProperty(): Collection
    {
        return MarketplaceAcquisition::query()
            ->with(['item.category', 'item.previews', 'sourceVersion'])
            ->latest('acquired_at')
            ->get();
    }

    public function acquisitionFor(MarketplaceItem $item): ?MarketplaceAcquisition
    {
        return $this->library->firstWhere('marketplace_item_id', $item->id);
    }

    public function previewFor(MarketplaceItem $item, MarketplacePreviewType $type = MarketplacePreviewType::THUMBNAIL): ?MarketplacePreview
    {
        return $item->previews->firstWhere('type', $type) ?? $item->previews->first();
    }

    public function previewDataUri(MarketplacePreview $preview): string
    {
        return app(MarketplacePreviewDelivery::class)->dataUri($preview);
    }

    public function acquire(string $slug): void
    {
        $item = app(MarketplaceCatalogue::class)->findPublishedBySlug($slug);

        try {
            app(AcquireMarketplaceItem::class)->handle($item);
            unset($this->library);
            Notification::make()->success()->title('Design added to your library')->body('Download the source package whenever you are ready to edit it.')->send();
        } catch (ValidationException) {
            Notification::make()->danger()->title('This design could not be added')->body('Check that it is still available, then try again.')->send();
        }
    }

    public function download(string $slug): ?StreamedResponse
    {
        $item = MarketplaceItem::query()->where('slug', $slug)->firstOrFail();

        try {
            return app(MarketplaceSourceDelivery::class)->issue($item)->response;
        } catch (ValidationException) {
            Notification::make()->danger()->title('Download unavailable')->body('This source package is not available right now. Your library record is safe.')->send();

            return null;
        }
    }
}
