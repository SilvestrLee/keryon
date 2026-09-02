<x-filament-panels::page>
    @php($churches = $this->churches())
    @include('filament.organization.partials.context')

    <header class="org-page-heading">
        <p class="org-eyebrow">Scoped directory</p>
        <h1>Churches across your responsibility</h1>
        <p>Review bounded operational state without entering Church workspaces or exposing local Church records.</p>
    </header>

    <section class="org-section" aria-labelledby="church-directory-heading">
        <div class="org-section__header">
            <div>
                <h2 id="church-directory-heading">{{ $churches->total() }} {{ str('Church')->plural($churches->total()) }}</h2>
                <p>Current governance assignments inside your authorized unit scope.</p>
            </div>
        </div>
        <div class="org-filters" role="search" aria-label="Filter Churches">
            <label>
                <span>Search Churches or Units</span>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search by name or Unit">
            </label>
            <label>
                <span>Operational state</span>
                <select wire:model.live="attention">
                    <option value="">All Churches</option>
                    <option value="needs_attention">Needs attention</option>
                    <option value="current">Current</option>
                </select>
            </label>
        </div>
        <div class="org-list">
            @forelse ($churches as $church)
                <article class="org-row org-row--church">
                    <div>
                        <a class="org-row__title org-row__link" href="{{ $this->detailUrl($church) }}" wire:navigate>{{ $church->name }}</a>
                        <div class="org-row__meta">{{ $church->unitType }} · {{ $church->unitName }}</div>
                    </div>
                    <div class="org-statuses" aria-label="Operational states for {{ $church->name }}">
                        <span class="org-badge">{{ $church->churchState }}</span>
                        <span class="org-badge {{ $church->websiteState === 'Live' ? 'org-badge--current' : 'org-badge--attention' }}">Website: {{ $church->websiteState }}</span>
                        <span class="org-badge {{ $church->domainState === 'Domain needs attention' || $church->domainState === 'Verification required' || $church->domainState === 'Domain disabled' ? 'org-badge--attention' : 'org-badge--current' }}">{{ $church->domainState }}</span>
                    </div>
                    <div class="org-actions">
                        <a class="org-text-link" href="{{ $this->detailUrl($church) }}" wire:navigate>View <span aria-hidden="true">→</span></a>
                        @if ($church->canManage)
                            <x-filament::button size="xs" color="gray" wire:click="mountAction('moveChurch', { assignment: {{ $church->assignmentId }} })">Move</x-filament::button>
                            <x-filament::button size="xs" color="danger" wire:click="mountAction('detachChurch', { assignment: {{ $church->assignmentId }} })">Detach</x-filament::button>
                        @endif
                    </div>
                </article>
            @empty
                <div class="org-empty">
                    <h3>{{ $search !== '' || $attention !== '' ? 'No Churches match these filters' : 'No Churches are currently assigned to this scope' }}</h3>
                    <p>{{ $search !== '' || $attention !== '' ? 'Clear or adjust the filters to review other Churches in your scope.' : 'Churches connected to this Organization will appear after Church-side acceptance.' }}</p>
                </div>
            @endforelse
        </div>
        @if ($churches->hasPages())
            <div class="org-pagination">{{ $churches->links() }}</div>
        @endif
    </section>

    @if ($this->relationshipHistory()->isNotEmpty())
        <section class="org-section">
            <div class="org-section__header">
                <div>
                    <h2>Relationship history</h2>
                    <p>Pending, rejected and ended governance records remain visible as evidence.</p>
                </div>
            </div>
            <div class="org-list">
                @foreach ($this->relationshipHistory() as $assignment)
                    <div class="org-row org-row--activity">
                        <div>
                            <div class="org-row__title">{{ $assignment->church->name }}</div>
                            <div class="org-row__meta">Requested {{ $assignment->requested_at->format('j M Y') }}</div>
                        </div>
                        <div class="org-row__scope">{{ $assignment->unit->name }}</div>
                        <span class="org-badge org-badge--{{ $assignment->status->value }}">{{ str($assignment->status->value)->headline() }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
