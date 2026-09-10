<x-public-website.proclaim-layout :$church :$brand :$logo :$mark :$serviceTimes :$socialLinks :$palette :$page :$seo :$preview :$navigation title="Events" :description="'Upcoming events at '.$church->name">
    <section class="pw-page-hero">
        <div class="pw-shell">
            <p class="pw-kicker">Events</p>
            <h1>What's happening at {{ $church->name }}.</h1>
        </div>
    </section>

    <section class="pw-section">
        <div class="pw-shell pw-event-list">
            @forelse ($events as $event)
                <article @class(['pw-event-card', 'is-featured' => $event->is_featured])>
                    @if ($event->publicImage)
                        <img src="{{ $event->publicImage['url'] }}" alt="{{ $event->publicImage['alt'] }}" width="{{ $event->publicImage['width'] ?: 1000 }}" height="{{ $event->publicImage['height'] ?: 700 }}" loading="lazy">
                    @endif
                    <div class="pw-event-copy">
                        @if ($event->is_featured)<p class="pw-kicker">Featured</p>@endif
                        <h2>{{ $event->title }}</h2>
                        <p class="pw-event-when">
                            <time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->format('l, F j, Y \a\t g:i A') }}</time>
                            @if ($event->venue)<span class="pw-event-venue"> · {{ $event->venue }}</span>@endif
                        </p>
                        @if ($event->summary)<div class="pw-prose">{!! nl2br(e($event->summary)) !!}</div>@endif
                        @if ($event->cta_label && $event->ctaUrl)
                            <a class="pw-button" href="{{ $event->ctaUrl }}" rel="noopener noreferrer">{{ $event->cta_label }}</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="pw-empty"><h2>No upcoming events right now.</h2><p>Please check back for updates from {{ $church->name }}.</p></div>
            @endforelse
        </div>
    </section>
</x-public-website.proclaim-layout>
