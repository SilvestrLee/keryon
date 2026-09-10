<x-public-website.proclaim-layout :$church :$brand :$logo :$mark :$serviceTimes :$socialLinks :$palette :$page :$seo :$preview :$navigation title="Messages" :description="'Messages from '.$church->name">
    <section class="pw-page-hero">
        <div class="pw-shell">
            <p class="pw-kicker">Messages</p>
            <h1>Watch and listen from wherever you are.</h1>
        </div>
    </section>

    <section class="pw-section">
        <div class="pw-shell pw-message-grid">
            @forelse ($messages as $message)
                <article class="pw-message-card">
                    @if ($message->publicImage)
                        <img src="{{ $message->publicImage['url'] }}" alt="{{ $message->publicImage['alt'] }}" width="{{ $message->publicImage['width'] ?: 800 }}" height="{{ $message->publicImage['height'] ?: 450 }}" loading="lazy">
                    @else
                        <div class="pw-image-fallback" aria-hidden="true"><span>{{ Str::substr($message->title, 0, 1) }}</span></div>
                    @endif
                    <div class="pw-message-copy">
                        <h2>{{ $message->title }}</h2>
                        <p class="pw-message-meta">
                            @if ($message->speaker){{ $message->speaker }}@endif
                            @if ($message->speaker && $message->message_date) · @endif
                            @if ($message->message_date)<time datetime="{{ $message->message_date->toDateString() }}">{{ $message->message_date->format('M j, Y') }}</time>@endif
                        </p>
                        @if ($message->scripture_reference)<p class="pw-role">{{ $message->scripture_reference }}</p>@endif
                        @if ($message->summary)<div class="pw-prose">{!! nl2br(e($message->summary)) !!}</div>@endif
                        @if ($message->mediaUrl)
                            <a class="pw-text-link" href="{{ $message->mediaUrl }}" rel="noopener noreferrer">Watch or listen <span aria-hidden="true">→</span></a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="pw-empty"><h2>No messages have been added yet.</h2><p>Please check back for updates from {{ $church->name }}.</p></div>
            @endforelse
        </div>
    </section>
</x-public-website.proclaim-layout>
