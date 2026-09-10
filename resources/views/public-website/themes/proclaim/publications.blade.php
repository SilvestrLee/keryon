<x-public-website.proclaim-layout :$church :$brand :$logo :$mark :$serviceTimes :$socialLinks :$palette :$page :$seo :$preview :$navigation title="Publications" :description="'Books and resources from '.$church->name">
    <section class="pw-page-hero">
        <div class="pw-shell">
            <p class="pw-kicker">Publications</p>
            <h1>Books and resources from {{ $church->name }}.</h1>
        </div>
    </section>

    <section class="pw-section pw-publications-section">
        <div class="pw-shell pw-publication-grid">
            @forelse ($publications as $publication)
                <article @class(['pw-publication-card', 'is-featured' => $publication->is_featured])>
                    <div class="pw-publication-cover">
                        @if ($publication->publicCover)
                            <img src="{{ $publication->publicCover['url'] }}" alt="{{ $publication->publicCover['alt'] }}" width="{{ $publication->publicCover['width'] ?: 500 }}" height="{{ $publication->publicCover['height'] ?: 750 }}" loading="lazy">
                        @else
                            <div class="pw-publication-cover-fallback" aria-hidden="true"><span>{{ Str::substr($publication->title, 0, 1) }}</span></div>
                        @endif
                    </div>
                    <div class="pw-publication-copy">
                        <p class="pw-kicker">{{ $publication->publication_type->label() }}</p>
                        <h2>{{ $publication->title }}</h2>
                        @if ($publication->author)<p class="pw-role">{{ $publication->author }}</p>@endif
                        @if ($publication->description)<div class="pw-prose">{!! nl2br(e($publication->description)) !!}</div>@endif
                        <div class="pw-publication-footer">
                            @if ($publication->price_text)<span class="pw-publication-price">{{ $publication->price_text }}</span>@endif
                            @if ($publication->purchaseUrl)
                                <a class="pw-button" href="{{ $publication->purchaseUrl }}" rel="noopener noreferrer">Get it</a>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="pw-empty"><h2>No publications are available yet.</h2><p>Please check back for updates from {{ $church->name }}.</p></div>
            @endforelse
        </div>
    </section>
</x-public-website.proclaim-layout>
