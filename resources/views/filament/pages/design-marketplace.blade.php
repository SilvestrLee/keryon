@php
    use App\Enums\MarketplaceAccessType;
    use App\Enums\MarketplacePreviewType;
    use App\Enums\MarketplacePublicationStatus;
    use App\Enums\MarketplaceSourceAvailability;

    $selected = $this->selectedProduct;
@endphp

<x-filament-panels::page>
    <main class="mp" aria-labelledby="marketplace-title">
        @if ($selected)
            @php
                $preview = $this->previewFor($selected, MarketplacePreviewType::PREVIEW);
                $source = $selected->currentSourceVersion();
                $acquisition = $this->acquisitionFor($selected);
                $psd = data_get($source?->compatibility_metadata, 'source_psd', []);
            @endphp
            <a href="{{ static::getUrl() }}" wire:navigate class="mp-back">
                <x-filament::icon icon="heroicon-o-arrow-left" class="h-4 w-4" />
                Marketplace
            </a>

            <section class="mp-detail" aria-labelledby="marketplace-title">
                <div class="mp-detail-media">
                    @if ($preview)
                        <img src="{{ $this->previewDataUri($preview) }}" alt="{{ $preview->alt_text }}">
                    @else
                        <div class="mp-preview-missing" role="img" aria-label="Preview unavailable">
                            <x-filament::icon icon="heroicon-o-photo" class="h-10 w-10" />
                            <span>Preview unavailable</span>
                        </div>
                    @endif
                </div>

                <div class="mp-detail-copy">
                    <p class="mp-kicker">{{ $selected->category->name }}</p>
                    <h1 id="marketplace-title">{{ $selected->title }}</h1>
                    <p class="mp-lede">{{ $selected->description ?? $selected->short_description }}</p>

                    <div class="mp-product-facts" aria-label="Product information">
                        <div><span>Access</span><strong>{{ $selected->access_type === MarketplaceAccessType::FREE ? 'Free for Keryon churches' : 'Premium' }}</strong></div>
                        <div><span>Source</span><strong>Editable PSD in ZIP</strong></div>
                        @if (data_get($psd, 'width') && data_get($psd, 'height'))
                            <div><span>Artwork</span><strong>{{ number_format(data_get($psd, 'width')) }} × {{ number_format(data_get($psd, 'height')) }} px</strong></div>
                        @endif
                        <div><span>Editing</span><strong>Photoshop or compatible editor</strong></div>
                    </div>

                    <aside class="mp-edit-note">
                        <x-filament::icon icon="heroicon-o-document-text" class="h-6 w-6" />
                        <div><strong>Editable PSD</strong><p>Download the source package and customize it using Adobe Photoshop or a compatible PSD editor.</p></div>
                    </aside>

                    @if (data_get($source?->font_metadata, 'declaration_status') === 'pending_publisher_declaration')
                        <p class="mp-disclosure">Font requirements are being confirmed. Font files are not included in the package.</p>
                    @endif

                    <div class="mp-action" aria-live="polite">
                        @if ($acquisition)
                            <div><span>In your library</span><strong>Ready to download</strong></div>
                            <button type="button" wire:click="download('{{ $selected->slug }}')" wire:loading.attr="disabled" class="mp-button mp-button-primary">
                                <span wire:loading.remove wire:target="download('{{ $selected->slug }}')">Download design</span>
                                <span wire:loading wire:target="download('{{ $selected->slug }}')">Preparing download</span>
                            </button>
                        @else
                            <div><span>Free</span><strong>No additional Marketplace charge</strong></div>
                            <button type="button" wire:click="acquire('{{ $selected->slug }}')" wire:loading.attr="disabled" class="mp-button mp-button-primary">
                                <span wire:loading.remove wire:target="acquire('{{ $selected->slug }}')">Get this design</span>
                                <span wire:loading wire:target="acquire('{{ $selected->slug }}')">Adding to library</span>
                            </button>
                        @endif
                    </div>
                </div>
            </section>
        @else
            <header class="mp-hero">
                <div>
                    <a href="{{ App\Filament\Pages\DesignStudio::getUrl() }}" wire:navigate class="mp-back">
                        <x-filament::icon icon="heroicon-o-arrow-left" class="h-4 w-4" />
                        Design
                    </a>
                    <p class="mp-kicker">Private Design Marketplace</p>
                    <h1 id="marketplace-title">Professional designs for church communication.</h1>
                    <p>Choose a source design, acquire it for your Church, and edit it in your preferred PSD application.</p>
                </div>
                <a href="#marketplace-library" class="mp-library-link">Your library <span>{{ $this->library->count() }}</span></a>
            </header>

            <section class="mp-catalogue" aria-labelledby="catalogue-title">
                <div class="mp-heading">
                    <h2 id="catalogue-title">Available designs</h2>
                    <p>A small, Keryon-curated collection. Every source is reviewed before release.</p>
                </div>

                @if ($this->catalogue->isEmpty())
                    <div class="mp-empty" role="status">
                        <x-filament::icon icon="heroicon-o-photo" class="h-8 w-8" />
                        <h3>New designs are being prepared</h3>
                        <p>The private catalogue does not have an available source right now.</p>
                    </div>
                @else
                    <div class="mp-grid">
                        @foreach ($this->catalogue as $item)
                            @php($preview = $this->previewFor($item))
                            @php($acquired = $this->acquisitionFor($item))
                            <a href="{{ static::getUrl(['product' => $item->slug]) }}" wire:navigate class="mp-card">
                                <span class="mp-card-media">
                                    @if ($preview)
                                        <img src="{{ $this->previewDataUri($preview) }}" alt="{{ $preview->alt_text }}">
                                    @else
                                        <span class="mp-preview-missing" role="img" aria-label="Preview unavailable"><x-filament::icon icon="heroicon-o-photo" class="h-8 w-8" /></span>
                                    @endif
                                </span>
                                <span class="mp-card-copy">
                                    <span class="mp-card-meta">{{ $item->category->name }} · PSD</span>
                                    <strong>{{ $item->title }}</strong>
                                    <small>{{ $item->short_description }}</small>
                                    <span class="mp-card-footer">
                                        <b>{{ $item->access_type === MarketplaceAccessType::FREE ? 'Free' : 'Premium' }}</b>
                                        <em>{{ $acquired ? 'In your library' : 'View design' }}</em>
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section id="marketplace-library" class="mp-library" aria-labelledby="library-title">
                <div class="mp-heading">
                    <h2 id="library-title">Marketplace library</h2>
                    <p>Source designs acquired by your active Church.</p>
                </div>

                @if ($this->library->isEmpty())
                    <div class="mp-empty mp-empty-library">
                        <x-filament::icon icon="heroicon-o-folder-open" class="h-8 w-8" />
                        <h3>Your design library is ready</h3>
                        <p>Designs you acquire from the Keryon Marketplace will appear here.</p>
                        <a href="#catalogue-title">Browse Marketplace</a>
                    </div>
                @else
                    <div class="mp-library-grid">
                        @foreach ($this->library as $acquisition)
                            @php($item = $acquisition->item)
                            @php($preview = $this->previewFor($item))
                            @php($available = $item->publication_status === MarketplacePublicationStatus::PUBLISHED && $item->currentSourceVersion()?->availability_status === MarketplaceSourceAvailability::AVAILABLE)
                            <article class="mp-library-card">
                                @if ($preview && $available)<img src="{{ $this->previewDataUri($preview) }}" alt="{{ $preview->alt_text }}">@endif
                                <div>
                                    <span>Acquired {{ $acquisition->acquired_at?->format('j M Y') }}</span>
                                    <h3>{{ $item->title }}</h3>
                                    @if ($available)
                                        <button type="button" wire:click="download('{{ $item->slug }}')" class="mp-text-action">Download design</button>
                                    @else
                                        <p class="mp-unavailable">Source temporarily unavailable. Your acquisition remains recorded.</p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </main>
</x-filament-panels::page>
