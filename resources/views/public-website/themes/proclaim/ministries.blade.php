<x-public-website.proclaim-layout :$church :$brand :$logo :$mark :$serviceTimes :$socialLinks :$palette :$page :$seo :$preview title="Ministries" :description="'Explore ministries at '.$church->name">
    <section class="pw-page-hero">
        <div class="pw-shell">
            <p class="pw-kicker">Ministries</p>
            <h1>Find a place to grow and belong.</h1>
        </div>
    </section>

    <section class="pw-section">
        <div class="pw-shell pw-ministry-list">
            @forelse ($ministries as $ministry)
                <article class="pw-ministry">
                    <div class="pw-ministry-copy">
                        <h2>{{ $ministry->name }}</h2>
                        @if ($ministry->description)<div class="pw-prose">{!! nl2br(e($ministry->description)) !!}</div>@endif
                    </div>
                    @if ($ministry->publicImage)
                        <img src="{{ $ministry->publicImage['url'] }}" alt="{{ $ministry->publicImage['alt'] }}" width="{{ $ministry->publicImage['width'] ?: 1000 }}" height="{{ $ministry->publicImage['height'] ?: 720 }}" loading="lazy">
                    @else
                        <div class="pw-ministry-mark" aria-hidden="true"><span>{{ Str::substr($ministry->name, 0, 1) }}</span></div>
                    @endif
                </article>
            @empty
                <div class="pw-empty"><h2>Ministry information is coming soon.</h2><p>Please check back for ways to connect at {{ $church->name }}.</p></div>
            @endforelse
        </div>
    </section>
</x-public-website.proclaim-layout>
