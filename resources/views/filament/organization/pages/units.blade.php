<x-filament-panels::page>
    @include('filament.organization.partials.context')

    <p class="org-intro">Units describe the hierarchy around Churches. Structural changes never alter Church tenancy or Church-owned records.</p>

    <section class="org-section">
        <div class="org-section__header">
            <div>
                <h2>Organization hierarchy</h2>
                <p>Active and historically visible Units inside your assigned scope.</p>
            </div>
        </div>

        <div class="org-list">
            @forelse ($this->units() as $unit)
                <div class="org-row">
                    <div class="org-tree-name">
                        <span class="org-tree-indent" style="--depth: {{ $unit->hierarchy_depth }}"></span>
                        @if ($unit->hierarchy_depth > 0)<span class="org-tree-branch">↳</span>@endif
                        <div>
                            <div class="org-row__title">{{ $unit->name }}</div>
                            <div class="org-row__meta">{{ $unit->type->label }} · {{ $unit->code }}</div>
                        </div>
                    </div>
                    <div class="org-row__scope">
                        <span class="org-badge {{ $unit->status->value === 'archived' ? 'org-badge--archived' : '' }}">{{ ucfirst($unit->status->value) }}</span>
                    </div>
                    <div class="org-actions">
                        @if (auth()->user()->can('update', $unit) && $unit->id !== $this->organization()->root_unit_id)
                            <x-filament::button size="xs" color="gray" wire:click="mountAction('moveUnit', { unit: {{ $unit->id }} })">Move</x-filament::button>
                            <x-filament::button size="xs" color="danger" wire:click="mountAction('archiveUnit', { unit: {{ $unit->id }} })">Archive</x-filament::button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="org-empty">
                    <h3>No Units yet</h3>
                    <p>Create your first organizational Unit to begin structuring your network.</p>
                </div>
            @endforelse
        </div>
    </section>

    <x-filament-actions::modals />
</x-filament-panels::page>
