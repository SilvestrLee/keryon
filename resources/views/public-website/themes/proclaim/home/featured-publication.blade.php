{{-- K-PROCLAIM-V1-001A — Home's single featured Publication (§16/§27 —
cover/editorial-led identity). Deliberately reuses the dedicated
/publications page's own card markup/classes verbatim (§27's "retain some
of the identity already established on its canonical page") rather than
inventing a parallel visual language for the same content type. --}}
<section class="pw-section pw-home-publication">
    <div class="pw-shell">
        <div class="pw-home-section-head">
            <h2>From our library</h2>
            <a class="pw-text-link" href="{{ $preview ? route('website.preview', ['page' => 'publications']) : '/publications' }}">All publications</a>
        </div>
        <article @class(['pw-publication-card', 'pw-home-publication-card', 'is-featured' => $homePublication->is_featured])>
            <div class="pw-publication-cover">
                @if ($homePublication->publicCover)
                    <img src="{{ $homePublication->publicCover['url'] }}" alt="{{ $homePublication->publicCover['alt'] }}" width="{{ $homePublication->publicCover['width'] ?: 500 }}" height="{{ $homePublication->publicCover['height'] ?: 750 }}" loading="lazy">
                @else
                    <div class="pw-publication-cover-fallback" aria-hidden="true"><span>{{ Str::substr($homePublication->title, 0, 1) }}</span></div>
                @endif
            </div>
            <div class="pw-publication-copy">
                <p class="pw-kicker">{{ $homePublication->publication_type->label() }}</p>
                <h3>{{ $homePublication->title }}</h3>
                @if ($homePublication->author)<p class="pw-role">{{ $homePublication->author }}</p>@endif
                @if ($homePublication->description)<div class="pw-prose">{!! nl2br(e($homePublication->description)) !!}</div>@endif
                <div class="pw-publication-footer">
                    @if ($homePublication->price_text)<span class="pw-publication-price">{{ $homePublication->price_text }}</span>@endif
                    @if ($homePublication->purchaseUrl)
                        <a class="pw-button" href="{{ $homePublication->purchaseUrl }}" rel="noopener noreferrer">Get it</a>
                    @endif
                </div>
            </div>
        </article>
    </div>
</section>
