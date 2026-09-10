{{-- K-PROCLAIM-V1-001A — Home's Giving pathway (§17/§27 — trust/CTA-led,
deliberately calm and secondary: no pressure-sell tone, no transactional
language, external destination only). --}}
<section class="pw-section pw-home-giving">
    <div class="pw-shell pw-home-giving-grid">
        <div>
            <p class="pw-kicker">Giving</p>
            <h2>{{ $homeGiving->headline ?: 'Give to '.$church->name }}</h2>
            @if ($homeGiving->body)<div class="pw-prose">{!! nl2br(e($homeGiving->body)) !!}</div>@endif
            @if ($homeGivingUrl)
                <a class="pw-button pw-button-dark" href="{{ $homeGivingUrl }}" rel="noopener noreferrer">{{ $homeGiving->cta_label ?: 'Give now' }}</a>
            @endif
        </div>
        @if ($homeGivingImage)
            <img class="pw-giving-image" src="{{ $homeGivingImage['url'] }}" alt="{{ $homeGivingImage['alt'] }}" width="{{ $homeGivingImage['width'] ?: 800 }}" height="{{ $homeGivingImage['height'] ?: 600 }}" loading="lazy">
        @endif
    </div>
</section>
