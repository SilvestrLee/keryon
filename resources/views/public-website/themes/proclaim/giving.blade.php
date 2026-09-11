<x-public-website.proclaim-layout :$church :$brand :$logo :$mark :$serviceTimes :$socialLinks :$palette :$page :$seo :$preview :$navigation title="Giving" :description="'Give to '.$church->name">
    <section class="pw-page-hero">
        <div class="pw-shell">
            <p class="pw-kicker">Giving</p>
            <h1>{{ $content?->headline ?: 'Support the ministry of '.$church->name }}</h1>
        </div>
    </section>

    <section class="pw-section pw-giving-section">
        <div class="pw-shell pw-giving-grid">
            <div class="pw-giving-copy">
                @if ($content?->body)<div class="pw-prose">{!! nl2br(e($content->body)) !!}</div>@endif
                @if ($content?->additional_instructions)
                    <div class="pw-giving-instructions">
                        <p class="pw-kicker">More ways to give</p>
                        <div class="pw-prose">{!! nl2br(e($content->additional_instructions)) !!}</div>
                    </div>
                @endif
                @if (($content?->cta_label ?? null) && $givingUrl)
                    <a class="pw-button" href="{{ $givingUrl }}" rel="noopener noreferrer">{{ $content->cta_label }}</a>
                @endif
                @if (! $content || (! $content->body && ! $content->headline))
                    <div class="pw-empty"><h2>Giving information is coming soon.</h2><p>Please check back for updates from {{ $church->name }}.</p></div>
                @endif
            </div>
            @if ($givingImage)
                <img class="pw-giving-image" src="{{ $givingImage['url'] }}" alt="{{ $givingImage['alt'] }}" width="{{ $givingImage['width'] ?: 900 }}" height="{{ $givingImage['height'] ?: 700 }}" loading="lazy">
            @endif
        </div>
    </section>
</x-public-website.proclaim-layout>
