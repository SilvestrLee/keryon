<x-filament-panels::page>
    <main class="central" data-platform-plane="central">
        <header class="central-hero">
            <div>
                <p class="central-hero__label">Platform Operations</p>
                <h1>Keryon Central</h1>
                <p>Launch-critical customer, delivery, domain, commercial, and provider evidence. Read-only by design.</p>
            </div>
            <div class="central-identity">
                <span>Signed in as</span>
                <strong>{{ auth()->user()->name }}</strong>
                <small>{{ $this->platformMembership()->role->label() }}</small>
            </div>
        </header>

        @php($attention = $this->attention())
        <section class="central-section"><div class="central-section__head"><h2>Needs attention</h2><p>Factual signals from canonical platform state.</p></div>
            @if(empty($attention))<div class="central-empty">No authorized attention items are currently visible.</div>@else<div class="central-attention">@foreach($attention as $item)<a href="{{ $item->url }}" class="central-attention__item"><span>{{ $item->category }}</span><strong>{{ $item->title }}</strong><p>{{ $item->reason }}</p></a>@endforeach</div>@endif
        </section>
        @php($activity = $this->recentActivity())
        @if($activity->isNotEmpty())<section class="central-section"><div class="central-section__head"><h2>Recent platform activity</h2><p>Immutable Central authority evidence.</p></div><div class="central-list">@foreach($activity as $event)<article class="central-row"><div><strong>{{ str($event->event_type->value)->replace('.',' ')->headline() }}</strong><small>{{ $event->actor?->name ?? 'Bootstrap process' }}</small></div><div><strong>{{ str($event->target_type->value)->headline() }}</strong><small>{{ $event->occurred_at->format('j M Y, H:i') }}</small></div></article>@endforeach</div></section>@endif
        <p class="central-production-gate">Privileged access is protected by mandatory multi-factor authentication and expires after inactivity.</p>
    </main>
</x-filament-panels::page>
