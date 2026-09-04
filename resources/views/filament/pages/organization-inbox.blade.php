<x-filament-panels::page>
    @php($deliveries = $this->deliveries())

    <main class="kc-hub kc-inbox">
        <header class="kc-hero">
            <div>
                <p class="kc-kicker">Communications</p>
                <h1>Organization Inbox</h1>
                <p>Communications and shared campaigns your Organization has shared with your Church.</p>
            </div>
        </header>

        <section class="kc-section" aria-labelledby="inbox-list-heading">
            <div class="kc-section-heading">
                <div>
                    <h2 id="inbox-list-heading">{{ $deliveries->total() }} {{ str('item')->plural($deliveries->total()) }}</h2>
                    <p>Everything your Organization has shared with your Church, and how your Church has responded.</p>
                </div>
            </div>

            <div class="kc-filters" role="search" aria-label="Filter Organization Inbox">
                <label>
                    <span>Search</span>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search by title or Organization">
                </label>
                <label>
                    <span>Status</span>
                    <select wire:model.live="stateFilter">
                        <option value="all">All</option>
                        <option value="available">Available</option>
                        <option value="accepted">Accepted</option>
                        <option value="declined">Declined</option>
                        <option value="expired">Expired</option>
                    </select>
                </label>
                <label>
                    <span>Type</span>
                    <select wire:model.live="kindFilter">
                        <option value="">All</option>
                        <option value="communication">Communications</option>
                        <option value="campaign">Shared campaigns</option>
                    </select>
                </label>
            </div>

            <div class="kc-inbox-list">
                @forelse ($deliveries as $item)
                    <article class="kc-inbox-row">
                        <div>
                            <a class="kc-inbox-row__title" href="{{ $this->detailUrl($item->uuid) }}" wire:navigate>{{ $item->title }}</a>
                            <div class="kc-inbox-row__meta">{{ $item->organizationName }} · {{ $item->kindLabel }} · v{{ $item->revisionVersion }}</div>
                            <div class="kc-inbox-row__meta">{{ $item->adaptationPolicyLabel }}</div>
                        </div>
                        <div class="kc-inbox-row__dates">
                            <span>Shared {{ \Illuminate\Support\Carbon::parse($item->availableAt)->format('j M Y') }}</span>
                            @if ($item->availableUntil)
                                <span>Available until {{ \Illuminate\Support\Carbon::parse($item->availableUntil)->format('j M Y') }}</span>
                            @endif
                        </div>
                        <div class="kc-inbox-row__actions">
                            <span class="kc-badge kc-badge--{{ str_replace('_', '-', $item->responseState) }}">{{ $item->responseStateLabel }}</span>
                            <a class="kc-text-link" href="{{ $this->detailUrl($item->uuid) }}" wire:navigate>Open <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                @empty
                    <div class="kc-clear-state" role="status">
                        <span aria-hidden="true"><x-filament::icon icon="heroicon-o-inbox" /></span>
                        <div>
                            @if ($search !== '' || $stateFilter !== 'all' || $kindFilter !== '')
                                <h3>No items match these filters</h3>
                                <p>Clear or adjust the filters to see other shared communications.</p>
                            @else
                                <h3>Nothing has been shared with your Church yet</h3>
                                <p>When your Organization shares a communication or campaign with your Church, it will appear here.</p>
                            @endif
                        </div>
                    </div>
                @endforelse
            </div>

            @if ($deliveries->hasPages())
                <div class="kc-pagination">{{ $deliveries->links() }}</div>
            @endif
        </section>
    </main>
</x-filament-panels::page>
