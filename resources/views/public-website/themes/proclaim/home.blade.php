<x-public-website.proclaim-layout
    :$church :$brand :$logo :$mark :$serviceTimes :$socialLinks :$palette :$page :$seo :$preview
    title="Home"
    :description="$content?->hero_subheading ?: 'Welcome to '.$church->name"
>
    <section @class(['pw-hero', 'has-image' => $heroImage])>
        @if ($heroImage)
            <img class="pw-hero-image" src="{{ $heroImage['url'] }}" alt="{{ $heroImage['alt'] }}" width="{{ $heroImage['width'] ?: 1600 }}" height="{{ $heroImage['height'] ?: 1000 }}">
            <div class="pw-hero-scrim" aria-hidden="true"></div>
        @endif
        <div class="pw-shell pw-hero-content">
            <p class="pw-kicker">Welcome to {{ $church->name }}</p>
            <h1>{{ $content?->hero_heading ?: $church->name }}</h1>
            @if ($content?->hero_subheading)
                <p class="pw-hero-copy">{{ $content->hero_subheading }}</p>
            @endif
            @if ($content?->hero_cta_label && $heroCtaUrl)
                <a class="pw-button" href="{{ $heroCtaUrl }}">{{ $content->hero_cta_label }}</a>
            @endif
        </div>
    </section>

    @if ($content?->welcome_heading || $content?->welcome_body)
        <section class="pw-section">
            <div class="pw-shell pw-welcome-grid">
                <h2>{{ $content?->welcome_heading ?: 'You are welcome here' }}</h2>
                @if ($content?->welcome_body)
                    <div class="pw-prose">{!! nl2br(e($content->welcome_body)) !!}</div>
                @endif
            </div>
        </section>
    @endif

    @if ($content?->scripture_text || $content?->scripture_reference)
        <section class="pw-scripture">
            <div class="pw-shell">
                @if ($content?->scripture_text)
                    <blockquote>“{{ $content->scripture_text }}”</blockquote>
                @endif
                @if ($content?->scripture_reference)
                    <p>{{ $content->scripture_reference }}</p>
                @endif
            </div>
        </section>
    @endif

    @if ($serviceTimes->isNotEmpty())
        <section class="pw-section pw-gather">
            <div class="pw-shell">
                <h2>Gather with us</h2>
                <div class="pw-service-grid">
                    @foreach ($serviceTimes as $service)
                        <article>
                            <h3>{{ $service->label }}</h3>
                            <p>{{ $service->day_of_week?->label() }} {{ $service->time }}</p>
                        </article>
                    @endforeach
                </div>
                <a class="pw-text-link" href="{{ route('church-website.contact', ['church' => $church->slug]) }}">Plan your visit</a>
            </div>
        </section>
    @endif
</x-public-website.proclaim-layout>
