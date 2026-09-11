<x-public-website.proclaim-layout :$church :$brand :$logo :$mark :$serviceTimes :$socialLinks :$palette :$page :$seo :$preview :$navigation title="About" :description="'Learn about '.$church->name">
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
                <a class="pw-button pw-button-dark" href="{{ $preview ? route('website.preview', ['page' => 'leadership']) : '/leadership' }}">Our leaders</a>
            </div>
        </section>
    @endif

    {{-- K-PROCLAIM-V1-001B §7 — the discovery audit's confirmed defect:
    unlike every other optional/collection page, About previously had no
    empty-state branch at all, falling straight from hero to footer when
    church_story/vision/mission/leadership_introduction are all absent.
    Matches the established `.pw-empty` grammar and warm, Church-named
    tone used by Leadership/Ministries/Events/Messages/Publications. --}}
    @if (! $content?->church_story && ! $content?->vision && ! $content?->mission && ! $content?->leadership_introduction)
        <section class="pw-section">
            <div class="pw-shell">
                <div class="pw-empty"><h2>Our story is coming soon.</h2><p>Please check back to learn more about {{ $church->name }}.</p></div>
            </div>
        </section>
    @endif
</x-public-website.proclaim-layout>
