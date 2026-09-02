<x-filament-panels::page>
    @php($unit = $this->unit())
    @php($children = $this->children())
    @php($churches = $this->churches())
    @include('filament.organization.partials.context')

    <nav class="org-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ \App\Filament\Organization\Pages\OrganizationUnits::getUrl(panel: 'organization') }}" wire:navigate>Structure</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ $unit->path }}</span>
    </nav>

    <header class="org-page-heading org-page-heading--detail">
        <p class="org-eyebrow">{{ $unit->type }} · {{ $unit->state }}</p>
        <h1>{{ $unit->name }}</h1>
        <p>Viewing: {{ $unit->path }}</p>
    </header>

    <div class="org-metrics org-metrics--compact">
        <section class="org-metric">
            <div class="org-metric__value">{{ $unit->childUnitCount }}</div>
            <div class="org-metric__label">Direct child Units</div>
        </section>
        <section class="org-metric">
            <div class="org-metric__value">{{ $unit->directChurchCount }}</div>
            <div class="org-metric__label">Direct Churches</div>
        </section>
        <section class="org-metric">
            <div class="org-metric__value">{{ $churches->total() }}</div>
            <div class="org-metric__label">Churches below this Unit</div>
        </section>
    </div>

    <section class="org-section" aria-labelledby="child-units-heading">
        <div class="org-section__header">
            <div>
                <p class="org-eyebrow">Next level</p>
                <h2 id="child-units-heading">Units directly below {{ $unit->name }}</h2>
                <p>Drill down one level at a time to keep large hierarchies usable.</p>
            </div>
        </div>
        <div class="org-list">
            @forelse ($children as $child)
                <a class="org-row org-row--linked" href="{{ $this->unitUrl($child) }}" wire:navigate>
                    <span>
                        <strong class="org-row__title">{{ $child->name }}</strong>
                        <small class="org-row__meta">{{ $child->type }} · {{ $child->code }}</small>
                    </span>
                    <span class="org-row__scope">{{ $child->childUnitCount }} child {{ str('Unit')->plural($child->childUnitCount) }} · {{ $child->directChurchCount }} direct {{ str('Church')->plural($child->directChurchCount) }}</span>
                    <span aria-hidden="true">→</span>
                </a>
            @empty
                <div class="org-empty org-empty--compact">
                    <h3>No lower Units in this scope</h3>
                    <p>Church assignments below this Unit remain visible in the directory beneath.</p>
                </div>
            @endforelse
        </div>
        @if ($children->hasPages())<div class="org-pagination">{{ $children->links() }}</div>@endif
    </section>

    <section class="org-section" aria-labelledby="unit-churches-heading">
        <div class="org-section__header">
            <div>
                <p class="org-eyebrow">Churches</p>
                <h2 id="unit-churches-heading">Churches in {{ $unit->name }} and permitted descendants</h2>
                <p>Operational state remains bounded to Organization-visible metadata.</p>
            </div>
        </div>
        <div class="org-list">
            @forelse ($churches as $church)
                <a class="org-row org-row--linked" href="{{ $this->churchUrl($church) }}" wire:navigate>
                    <span>
                        <strong class="org-row__title">{{ $church->name }}</strong>
                        <small class="org-row__meta">{{ $church->unitName }}</small>
                    </span>
                    <span class="org-statuses">
                        <span class="org-badge {{ $church->websiteState === 'Live' ? 'org-badge--current' : 'org-badge--attention' }}">Website: {{ $church->websiteState }}</span>
                        <span class="org-badge {{ $church->needsAttention() ? 'org-badge--attention' : 'org-badge--current' }}">{{ $church->needsAttention() ? 'Needs attention' : 'Current' }}</span>
                    </span>
                    <span aria-hidden="true">→</span>
                </a>
            @empty
                <div class="org-empty org-empty--compact">
                    <h3>No Churches are assigned below this Unit</h3>
                    <p>Accepted Church assignments will appear here.</p>
                </div>
            @endforelse
        </div>
        @if ($churches->hasPages())<div class="org-pagination">{{ $churches->links() }}</div>@endif
    </section>
</x-filament-panels::page>
