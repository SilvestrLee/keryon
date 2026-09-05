@php($assets = $this->assets())

<x-filament-panels::page>
    <main class="kc-hub kc-media">
        <header class="kc-hero">
            <div>
                <p class="kc-kicker">Communications</p>
                <h1>Media Library</h1>
                <p>Images your Church has uploaded, or that Design, Website, and Organization imports have created — all in one place.</p>
            </div>
        </header>

        <section class="kc-section" aria-labelledby="media-list-heading">
            <div class="kc-section-heading">
                <div>
                    <h2 id="media-list-heading">{{ $assets->total() }} {{ str('item')->plural($assets->total()) }}</h2>
                    <p>Find and reuse the visual assets that already belong to your Church.</p>
                </div>
                <div><x-filament::button icon="heroicon-o-arrow-up-tray" wire:click="mountAction('uploadMedia')">Upload media</x-filament::button></div>
            </div>

            <div class="kc-media-toolbar" role="search" aria-label="Filter Media Library">
                <label class="kc-filters" style="flex:1; min-width: 14rem;">
                    <span>Search</span>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search by filename or alt text">
                </label>
                <div class="kc-filters">
                    <label>
                        <span>Source</span>
                        <select wire:model.live="provenanceFilter">
                            <option value="">All</option>
                            @foreach ($this->provenanceOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>Rights</span>
                        <select wire:model.live="rightsFilter">
                            <option value="">All</option>
                            @foreach ($this->rightsOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>Sort</span>
                        <select wire:model.live="sort">
                            <option value="newest">Newest first</option>
                            <option value="oldest">Oldest first</option>
                            <option value="name">Filename A–Z</option>
                        </select>
                    </label>
                </div>
            </div>

            @if ($assets->isEmpty())
                <div class="kc-clear-state" role="status">
                    <span aria-hidden="true"><x-filament::icon icon="heroicon-o-photo" /></span>
                    <div>
                        @if ($search !== '' || $provenanceFilter !== '' || $rightsFilter !== '')
                            <h3>No media matches these filters</h3>
                            <p>Clear or adjust the filters to see other assets.</p>
                        @else
                            <h3>No media yet</h3>
                            <p>Media you upload or create through Brand, Design, Website, and other Keryon tools will appear here.</p>
                        @endif
                    </div>
                    <div><x-filament::button icon="heroicon-o-arrow-up-tray" wire:click="mountAction('uploadMedia')">Upload media</x-filament::button></div>
                </div>
            @else
                <div class="kc-media-grid">
                    @foreach ($assets as $asset)
                        <a href="{{ $this->detailUrl($asset) }}" wire:navigate class="kc-media-card" data-media-key="{{ $asset->uuid }}">
                            <div class="kc-media-card__thumb">
                                <img src="{{ $this->previewUrl($asset) }}" alt="{{ $asset->alt_text ?: '' }}" loading="lazy">
                            </div>
                            <div class="kc-media-card__body">
                                <span class="kc-media-card__name" title="{{ $asset->original_filename }}">{{ $asset->original_filename }}</span>
                                <span class="kc-media-card__meta">{{ $this->provenanceLabel($asset) }}</span>
                                <span class="kc-media-card__meta">
                                    @if ($asset->width && $asset->height)
                                        {{ $asset->width }}×{{ $asset->height }} ·
                                    @endif
                                    {{ $this->formattedSize($asset) }}
                                </span>
                                <div class="kc-media-card__footer">
                                    <span class="kc-media-rights kc-media-rights--{{ str($this->rightsLabel($asset))->lower()->slug() }}">{{ $this->rightsLabel($asset) }}</span>
                                    <span class="kc-media-card__meta">{{ $asset->created_at->format('j M Y') }}</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="kc-media-pagination">
                    {{ $assets->links() }}
                </div>
            @endif
        </section>

        <x-filament-actions::modals />
    </main>
</x-filament-panels::page>
