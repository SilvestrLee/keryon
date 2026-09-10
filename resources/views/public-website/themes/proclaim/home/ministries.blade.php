{{-- K-PROCLAIM-V1-001A — Home's bounded Ministries discovery teaser
(§15/§27 — belonging/discovery-led identity). Deliberately a compact
card grid, distinct from the dedicated /ministries page's full-detail row
layout — Home shows where a visitor might belong, not the whole story. --}}
<section class="pw-section pw-home-ministries">
    <div class="pw-shell">
        <div class="pw-home-section-head">
            <h2>Find your place</h2>
            <a class="pw-text-link" href="{{ $preview ? route('website.preview', ['page' => 'ministries']) : '/ministries' }}">All ministries</a>
        </div>
        <div class="pw-home-ministry-grid">
            @foreach ($homeMinistries as $ministry)
                <article class="pw-home-ministry-card">
                    @if ($ministry->publicImage)
                        <img src="{{ $ministry->publicImage['url'] }}" alt="{{ $ministry->publicImage['alt'] }}" width="{{ $ministry->publicImage['width'] ?: 480 }}" height="{{ $ministry->publicImage['height'] ?: 360 }}" loading="lazy">
                    @else
                        <div class="pw-ministry-mark" aria-hidden="true"><span>{{ Str::substr($ministry->name, 0, 1) }}</span></div>
                    @endif
                    <h3>{{ $ministry->name }}</h3>
                </article>
            @endforeach
        </div>
    </div>
</section>
