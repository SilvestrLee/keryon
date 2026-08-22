<x-public-website.proclaim-layout :$church :$brand :$logo :$mark :$serviceTimes :$socialLinks :$palette :$page :$seo :$preview title="Contact" :description="'Contact and visit '.$church->name">
    <section class="pw-page-hero">
        <div class="pw-shell">
            <p class="pw-kicker">Visit and connect</p>
            <h1>We would love to welcome you.</h1>
        </div>
    </section>

    <section class="pw-section">
        <div class="pw-shell pw-contact-grid">
            <div class="pw-contact-primary">
                <h2>Contact {{ $church->name }}</h2>
                @if ($church->address)<address>{{ $church->address }}</address>@endif
                @if ($church->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $church->phone) }}">{{ $church->phone }}</a>@endif
                @if ($church->email)<a href="mailto:{{ $church->email }}">{{ $church->email }}</a>@endif
                @if ($mapUrl)<a class="pw-text-link" href="{{ $mapUrl }}" rel="noopener noreferrer">View map and directions</a>@endif
            </div>

            <div class="pw-contact-details">
                @if ($serviceTimes->isNotEmpty())
                    <section><h2>Service times</h2><ul>@foreach ($serviceTimes as $service)<li><strong>{{ $service->label }}</strong><span>{{ $service->day_of_week?->label() }} {{ $service->time }}</span></li>@endforeach</ul></section>
                @endif
                @if ($content?->office_hours)
                    <section><h2>Office hours</h2><div class="pw-prose">{!! nl2br(e($content->office_hours)) !!}</div></section>
                @endif
                @if ($socialLinks->isNotEmpty())
                    <section><h2>Follow along</h2><div class="pw-contact-socials">@foreach ($socialLinks as $social)<a href="{{ $social->publicUrl }}" rel="noopener noreferrer">{{ $social->platform->label() }}</a>@endforeach</div></section>
                @endif
            </div>
        </div>
    </section>
</x-public-website.proclaim-layout>
