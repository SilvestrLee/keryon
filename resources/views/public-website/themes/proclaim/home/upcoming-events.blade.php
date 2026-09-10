{{-- K-PROCLAIM-V1-001A — Home's bounded Events teaser. Only rendered by
home.blade.php when $homeEvents is non-empty (enablement + content already
gated in ProclaimTheme::renderData()). Reuses the exact card language the
dedicated /events page already established (§27 — date/time-led identity). --}}
<section class="pw-section pw-home-events">
    <div class="pw-shell">
        <div class="pw-home-section-head">
            <h2>Upcoming</h2>
            <a class="pw-text-link" href="{{ $preview ? route('website.preview', ['page' => 'events']) : '/events' }}">See all events</a>
        </div>
        <div class="pw-home-event-list">
            @foreach ($homeEvents as $event)
                <article @class(['pw-event-card', 'pw-home-event-card', 'is-featured' => $event->is_featured])>
                    @if ($event->publicImage)
                        <img src="{{ $event->publicImage['url'] }}" alt="{{ $event->publicImage['alt'] }}" width="{{ $event->publicImage['width'] ?: 1000 }}" height="{{ $event->publicImage['height'] ?: 700 }}" loading="lazy">
                    @endif
                    <div class="pw-event-copy">
                        <h3>{{ $event->title }}</h3>
                        <p class="pw-event-when"><time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->format('D, M j \a\t g:i A') }}</time>
                            @if ($event->venue)<span class="pw-event-venue"> &middot; {{ $event->venue }}</span>@endif
                        </p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
