<x-filament-panels::page>
    @include('filament.organization.partials.context')

    <p class="org-intro">
        Review the Organization structure and the governance relationships you are authorized to manage. Church operations remain inside each Church workspace.
    </p>

    <div class="org-metrics">
        <section class="org-metric org-metric--identity">
            <div class="org-metric__label">Organization</div>
            <div class="org-metric__value">{{ $this->organization()->name }}</div>
            <div class="org-metric__detail">Status: {{ ucfirst($this->organization()->status->value) }}</div>
        </section>
        <section class="org-metric">
            <div class="org-metric__label">Units in your scope</div>
            <div class="org-metric__value">{{ $this->unitCount() }}</div>
            <div class="org-metric__detail">Active hierarchy only</div>
        </section>
        <section class="org-metric">
            <div class="org-metric__label">Churches in your scope</div>
            <div class="org-metric__value">{{ $this->churchCount() }}</div>
            <div class="org-metric__detail">Current governance assignments</div>
        </section>
    </div>

    @if ($this->staffCount() !== null)
        <section class="org-section">
            <div class="org-section__header">
                <div>
                    <h2>Organization staff</h2>
                    <p>{{ $this->staffCount() }} active staff members hold Organization authority.</p>
                </div>
                <x-filament::button color="gray" tag="a" href="{{ \App\Filament\Organization\Pages\OrganizationStaff::getUrl(panel: 'organization') }}" wire:navigate>
                    Manage staff
                </x-filament::button>
            </div>
        </section>
    @endif

    @if ($this->recentActivity()->isNotEmpty())
        <section class="org-section">
            <div class="org-section__header">
                <div>
                    <h2>Recent governance activity</h2>
                    <p>Bounded Organization changes. No Church operational data is included.</p>
                </div>
            </div>
            <div class="org-list">
                @foreach ($this->recentActivity() as $event)
                    <div class="org-row">
                        <div>
                            <div class="org-row__title">{{ str($event->event_type->value)->after('organization.')->replace('_', ' ')->headline() }}</div>
                            <div class="org-row__meta">{{ $event->occurred_at->format('j M Y, H:i') }}</div>
                        </div>
                        <div class="org-row__scope">{{ str($event->subject_type->value)->replace('_', ' ')->headline() }}</div>
                        <span class="org-badge">Recorded</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-filament-panels::page>
