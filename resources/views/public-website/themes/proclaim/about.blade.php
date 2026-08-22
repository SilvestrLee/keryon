<x-public-website.proclaim-layout :$church :$brand :$logo :$mark :$serviceTimes :$socialLinks :$palette :$page :$seo :$preview title="About" :description="'Learn about '.$church->name">
    <section class="pw-page-hero">
        <div class="pw-shell">
            <p class="pw-kicker">Our church</p>
            <h1>Rooted in faith.<br>Present in community.</h1>
        </div>
    </section>

    @if ($content?->church_story)
        <section class="pw-section">
            <div class="pw-shell pw-story-grid">
                <h2>Our story</h2>
                <div class="pw-prose">{!! nl2br(e($content->church_story)) !!}</div>
            </div>
        </section>
    @endif

    @if ($content?->vision || $content?->mission)
        <section class="pw-section pw-beliefs">
            <div class="pw-shell pw-belief-grid">
                @if ($content?->vision)
                    <article><h2>Vision</h2><div class="pw-prose">{!! nl2br(e($content->vision)) !!}</div></article>
                @endif
                @if ($content?->mission)
                    <article><h2>Mission</h2><div class="pw-prose">{!! nl2br(e($content->mission)) !!}</div></article>
                @endif
            </div>
        </section>
    @endif

    @if ($content?->leadership_introduction)
        <section class="pw-section">
            <div class="pw-shell pw-invitation">
                <div><h2>Meet our leadership</h2><div class="pw-prose">{!! nl2br(e($content->leadership_introduction)) !!}</div></div>
                <a class="pw-button pw-button-dark" href="{{ route('church-website.leadership', ['church' => $church->slug]) }}">Our leaders</a>
            </div>
        </section>
    @endif
</x-public-website.proclaim-layout>
