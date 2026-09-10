{{-- K-PROCLAIM-V1-001A — Home's single featured/latest Message teaser.
Reuses the Messages page's own image/fallback and meta-line grammar
(§27 — teaching/media-led identity). --}}
<section class="pw-section pw-home-message">
    <div class="pw-shell pw-home-message-grid">
        <div>
            @if ($homeMessage->publicImage)
                <img src="{{ $homeMessage->publicImage['url'] }}" alt="{{ $homeMessage->publicImage['alt'] }}" width="{{ $homeMessage->publicImage['width'] ?: 800 }}" height="{{ $homeMessage->publicImage['height'] ?: 450 }}" loading="lazy">
            @else
                <div class="pw-image-fallback" aria-hidden="true" style="aspect-ratio: 16/9;"><span>{{ Str::substr($homeMessage->title, 0, 1) }}</span></div>
            @endif
        </div>
        <div>
            <p class="pw-kicker">Latest message</p>
            <h2>{{ $homeMessage->title }}</h2>
            <p class="pw-message-meta">
                @if ($homeMessage->speaker){{ $homeMessage->speaker }}@endif
                @if ($homeMessage->speaker && $homeMessage->message_date) &middot; @endif
                @if ($homeMessage->message_date)<time datetime="{{ $homeMessage->message_date->toDateString() }}">{{ $homeMessage->message_date->format('M j, Y') }}</time>@endif
            </p>
            @if ($homeMessage->summary)<div class="pw-prose">{!! nl2br(e($homeMessage->summary)) !!}</div>@endif
            <a class="pw-text-link" href="{{ $preview ? route('website.preview', ['page' => 'messages']) : '/messages' }}">All messages</a>
        </div>
    </div>
</section>
