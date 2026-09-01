<x-filament-panels::page>
    <main class="central">
        <section class="central-section">
            <div class="central-section__head"><h2>Platform authority evidence</h2><p>Immutable staff-governance events. Customer content and secrets are never included.</p></div>
            @if($this->events()->isEmpty())
                <div class="central-empty">No platform audit events have been recorded.</div>
            @else
                <div class="central-list">
                    @foreach($this->events() as $event)
                        <article class="central-row central-audit-row">
                            <div><strong>{{ str($event->event_type->value)->replace('.', ' ')->headline() }}</strong><small>{{ $event->uuid }}</small></div>
                            <div><strong>{{ $event->actor?->name ?? 'Bootstrap process' }}</strong><small>{{ $event->actor?->email ?? 'No authenticated actor' }}</small></div>
                            <div><strong>{{ str($event->target_type->value)->replace('_', ' ')->headline() }}</strong><small>ID {{ $event->target_id }}</small></div>
                            <div><strong>{{ $event->reason_category ? str($event->reason_category->value)->replace('_', ' ')->headline() : 'No category' }}</strong><small>{{ $event->occurred_at->format('j M Y, H:i') }}</small></div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </main>
</x-filament-panels::page>
