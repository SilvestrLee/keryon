<x-filament-panels::page>
    @php($communications = $this->communications())
    @php($attention = $this->attention())
    @include('filament.organization.partials.context')

    <header class="org-page-heading">
        <p class="org-eyebrow">Communications</p>
        <h1>Coordinate messages and campaign resources</h1>
        <p>Prepare announcements, guidance and shared campaign resources for Churches across your authorized scope. Distribution is configured in a later step.</p>
    </header>

    @if ($attention['awaiting_review'] > 0 || $attention['changes_requested'] > 0)
        <section class="org-attention" aria-labelledby="communications-attention-heading">
            <div class="org-section__header">
                <div class="org-attention__heading">
                    <div>
                        <p class="org-eyebrow">Attention</p>
                        <h2 id="communications-attention-heading">
                            @php($parts = [])
                            @if ($attention['awaiting_review'] > 0) @php($parts[] = $attention['awaiting_review'].' awaiting review') @endif
                            @if ($attention['changes_requested'] > 0) @php($parts[] = $attention['changes_requested'].' changes requested') @endif
                            {{ implode(' · ', $parts) }}
                        </h2>
                        <p>Items that need your review or a resubmission, within your authorized scope.</p>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="org-section" aria-labelledby="communications-list-heading">
        <div class="org-section__header">
            <div>
                <h2 id="communications-list-heading">{{ $communications->total() }} {{ str('communication')->plural($communications->total()) }}</h2>
                <p>Draft, in review, changes requested, approved and closed items you are authorized to see.</p>
            </div>
        </div>

        <div class="org-filters" role="search" aria-label="Filter communications">
            <label>
                <span>Search by title</span>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search communications">
            </label>
            <label>
                <span>Type</span>
                <select wire:model.live="kindFilter">
                    <option value="">All</option>
                    <option value="communication">Communications</option>
                    <option value="campaign">Shared campaigns</option>
                </select>
            </label>
            <label>
                <span>Status</span>
                <select wire:model.live="stateFilter">
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="in_review">In review</option>
                    <option value="changes_requested">Changes requested</option>
                    <option value="approved">Approved</option>
                    <option value="closed">Closed</option>
                </select>
            </label>
        </div>

        <div class="org-list">
            @forelse ($communications as $item)
                <article class="org-row">
                    <div>
                        <a class="org-row__title org-row__link" href="{{ \App\Filament\Organization\Pages\OrganizationCommunicationDetail::getUrl(['record' => $item->id], panel: 'organization') }}" wire:navigate>{{ $item->title }}</a>
                        <div class="org-row__meta">{{ $item->kindLabel }} · v{{ $item->revisionVersion }} · {{ $item->governingUnitPath }}</div>
                    </div>
                    <div class="org-row__scope">
                        {{ $item->materialsCount }} {{ str('resource')->plural($item->materialsCount) }}
                        @if ($item->assetsCount > 0) · {{ $item->assetsCount }} {{ str('asset')->plural($item->assetsCount) }} @endif
                    </div>
                    <div class="org-actions">
                        <span class="org-badge org-badge--{{ str($item->displayState)->replace('_', '-') }}">{{ $item->displayStateLabel }}</span>
                        <a class="org-text-link" href="{{ \App\Filament\Organization\Pages\OrganizationCommunicationDetail::getUrl(['record' => $item->id], panel: 'organization') }}" wire:navigate>Open <span aria-hidden="true">→</span></a>
                    </div>
                </article>
            @empty
                <div class="org-empty">
                    @if ($search !== '' || $kindFilter !== '' || $stateFilter !== '')
                        <h3>No communications match these filters</h3>
                        <p>Clear or adjust the filters to see other items in your authorized scope.</p>
                    @else
                        <h3>Prepare your first organization communication</h3>
                        <p>Create announcements, campaign resources and guidance that can later be shared with Churches in your authorized organization scope.</p>
                        @if ($this->canCreate())
                            <div style="margin-top: 1rem;">
                                <x-filament::button wire:click="mountAction('createCommunication')">Create communication</x-filament::button>
                            </div>
                        @endif
                    @endif
                </div>
            @endforelse
        </div>

        @if ($communications->hasPages())
            <div class="org-pagination">{{ $communications->links() }}</div>
        @endif
    </section>

    <x-filament-actions::modals />
</x-filament-panels::page>
