<x-filament-panels::page>
    @php($units = $this->units())
    @include('filament.organization.partials.context')

    <header class="org-page-heading">
        <p class="org-eyebrow">Organization structure</p>
        <h1>Navigate the hierarchy you are responsible for</h1>
        <p>Drill into permitted Units and Churches without loading or revealing the complete Organization tree.</p>
    </header>

    <section class="org-section" aria-labelledby="assigned-scopes-heading">
        <div class="org-section__header">
            <div>
                <p class="org-eyebrow">Start here</p>
                <h2 id="assigned-scopes-heading">Assigned scope {{ count($this->scopeRoots()) === 1 ? 'root' : 'roots' }}</h2>
                <p>Each entry is an explicit role scope. Descendant visibility follows the existing closure-table authorization model.</p>
            </div>
        </div>
        <div class="org-scope-list">
            @forelse ($this->scopeRoots() as $unit)
                <a class="org-scope-card org-scope-card--link" href="{{ $this->detailUrl($unit) }}" wire:navigate>
                    <span>{{ $unit->type }}</span>
                    <strong>{{ $unit->path }}</strong>
                    <small>{{ $unit->childUnitCount }} direct {{ str('Unit')->plural($unit->childUnitCount) }} · {{ $unit->directChurchCount }} direct {{ str('Church')->plural($unit->directChurchCount) }}</small>
                </a>
            @empty
                <div class="org-empty org-empty--compact"><p>No active responsibility scope is assigned.</p></div>
            @endforelse
        </div>
    </section>

    <section class="org-section" aria-labelledby="hierarchy-heading">
        <div class="org-section__header">
            <div>
                <h2 id="hierarchy-heading">{{ $units->total() }} visible {{ str('Unit')->plural($units->total()) }}</h2>
                <p>Searchable, paginated Units inside your assigned scope.</p>
            </div>
        </div>
        <div class="org-filters" role="search" aria-label="Filter Organization Units">
            <label>
                <span>Search Units</span>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Name, code or parent">
            </label>
            <label>
                <span>Unit state</span>
                <select wire:model.live="state">
                    <option value="active">Active</option>
                    <option value="archived">Archived</option>
                    <option value="">All states</option>
                </select>
            </label>
        </div>

        <div class="org-list">
            @forelse ($units as $unit)
                <article class="org-row org-row--unit">
                    <div>
                        <a class="org-row__title org-row__link" href="{{ $this->detailUrl($unit) }}" wire:navigate>{{ $unit->name }}</a>
                        <div class="org-row__meta">{{ $unit->type }} · {{ $unit->code }}</div>
                    </div>
                    <div class="org-row__scope">
                        <span>{{ $unit->parentName ? 'Within '.$unit->parentName : 'Organization root' }}</span>
                        <small>{{ $unit->childUnitCount }} child {{ str('Unit')->plural($unit->childUnitCount) }} · {{ $unit->directChurchCount }} direct {{ str('Church')->plural($unit->directChurchCount) }}</small>
                    </div>
                    <div class="org-actions">
                        <span class="org-badge {{ $unit->state === 'Archived' ? 'org-badge--archived' : 'org-badge--current' }}">{{ $unit->state }}</span>
                        <a class="org-text-link" href="{{ $this->detailUrl($unit) }}" wire:navigate>Explore <span aria-hidden="true">→</span></a>
                        @if ($unit->canManage && $unit->id !== $this->organization()->root_unit_id)
                            <x-filament::button size="xs" color="gray" wire:click="mountAction('moveUnit', { unit: {{ $unit->id }} })">Move</x-filament::button>
                            <x-filament::button size="xs" color="danger" wire:click="mountAction('archiveUnit', { unit: {{ $unit->id }} })">Archive</x-filament::button>
                        @endif
                    </div>
                </article>
            @empty
                <div class="org-empty">
                    <h3>{{ $search !== '' || $state !== 'active' ? 'No Units match these filters' : 'No Units are currently assigned to this scope' }}</h3>
                    <p>{{ $search !== '' || $state !== 'active' ? 'Clear or adjust the filters to review other permitted Units.' : 'An Organization administrator can assign a responsibility scope when the structure is ready.' }}</p>
                </div>
            @endforelse
        </div>
        @if ($units->hasPages())
            <div class="org-pagination">{{ $units->links() }}</div>
        @endif
    </section>

    <x-filament-actions::modals />
</x-filament-panels::page>
