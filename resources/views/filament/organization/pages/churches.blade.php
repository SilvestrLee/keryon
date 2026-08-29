<x-filament-panels::page>
    @include('filament.organization.partials.context')

    <p class="org-intro">This directory shows bounded governance relationships only. Congregation, Care, communications, media and Church settings remain inside each Church workspace.</p>

    <section class="org-section">
        <div class="org-section__header">
            <div>
                <h2>Currently attached Churches</h2>
                <p>Churches with an active assignment inside your scope.</p>
            </div>
        </div>
        <div class="org-list">
            @forelse ($this->churches() as $church)
                @php($assignment = $church->currentOrganizationAssignment)
                <div class="org-row">
                    <div>
                        <div class="org-row__title">{{ $church->name }}</div>
                        <div class="org-row__meta">Church status: {{ $church->is_active ? 'Active' : 'Inactive' }}</div>
                    </div>
                    <div class="org-row__scope">
                        {{ $assignment->unit->name }}<br>
                        <span class="org-row__meta">Effective {{ $assignment->effective_at?->format('j M Y') }}</span>
                    </div>
                    <div class="org-actions">
                        @if (auth()->user()->can('detach', $assignment))
                            <x-filament::button size="xs" color="gray" wire:click="mountAction('moveChurch', { assignment: {{ $assignment->id }} })">Move</x-filament::button>
                            <x-filament::button size="xs" color="danger" wire:click="mountAction('detachChurch', { assignment: {{ $assignment->id }} })">Detach</x-filament::button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="org-empty">
                    <h3>No Churches attached</h3>
                    <p>Churches connected to this Organization will appear here after Church-side acceptance.</p>
                </div>
            @endforelse
        </div>
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
                    <div class="org-row">
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
