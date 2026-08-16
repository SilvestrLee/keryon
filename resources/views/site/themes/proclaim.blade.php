<x-layouts.site
    title="Proclaim — Keryon Website Themes"
    description="Proclaim is a bold, editorial, message-led Keryon website theme for churches that want preaching, worship, and community to lead their digital presence."
>
    {{-- Section 01 — Theme identity / hero --}}
    <section class="mx-auto max-w-3xl px-4 pb-12 pt-16 text-center sm:px-6 lg:px-8 lg:pt-24">
        <p class="text-sm font-semibold uppercase tracking-wide text-accent">Keryon Website Themes</p>
        <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
            <h1 class="text-4xl font-bold leading-tight tracking-tight text-ink sm:text-5xl">Proclaim</h1>
            <x-site.theme-status status="preview" />
        </div>
        <p class="mx-auto mt-6 max-w-[52ch] text-lg leading-relaxed text-ink/70">
            Bold. Editorial. Message-led. A contemporary church website experience designed for churches that want preaching, worship, and community to lead their digital presence.
        </p>
    </section>

    {{-- Section 02 — Large theme-preview zone --}}
    <section class="mx-auto max-w-5xl px-4 pb-20 sm:px-6 lg:px-8">
        <x-site.interface-preview
            label="Proclaim"
            tint="primary"
            aspect="wide"
            caption="Proclaim's live preview is still in design and development."
        />
    </section>

    {{-- Section 03 — Visual character / designed for --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-5xl px-4 py-20 sm:px-6 lg:grid lg:grid-cols-2 lg:gap-16 lg:px-8">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Designed for</h2>
                <ul class="mt-6 space-y-3 text-sm text-ink/70">
                    @foreach ([
                        'Churches that lead with preaching and teaching',
                        'Worship-centered communities',
                        'Churches wanting a bold, editorial digital presence',
                    ] as $item)
                        <li class="flex items-start gap-3">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-12 lg:mt-0">
                <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Visual character</h2>
                <p class="mt-6 text-sm leading-relaxed text-ink/70">
                    Proclaim is being designed around strong typography, editorial imagery, and message-led hierarchy — a website that reads like a considered publication rather than a directory of pages.
                </p>
            </div>
        </div>
    </section>

    {{-- Section 04 — Supported website experiences --}}
    <section class="mx-auto max-w-5xl px-4 py-20 sm:px-6 lg:px-8">
        <h2 class="text-center text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
            Supported website experiences
        </h2>
        <p class="mx-auto mt-4 max-w-[56ch] text-center text-base leading-relaxed text-ink/70">
            Proclaim presents Keryon's fixed website sections in its own visual language.
        </p>

        <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (['Hero Banner', 'Pastor Welcome', 'Service Times', 'Ministries', 'Footer'] as $section)
                <div class="rounded-card border border-slate-200/80 bg-white p-6">
                    <p class="text-sm font-medium text-ink">{{ $section }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Section 05 — Theme controls vs church controls --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-5xl px-4 py-20 sm:px-6 lg:grid lg:grid-cols-2 lg:gap-16 lg:px-8">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Proclaim controls</h2>
                <ul class="mt-6 space-y-3 text-sm text-ink/70">
                    @foreach ([
                        'Layout and visual composition',
                        'Navigation presentation',
                        'Responsive behaviour',
                        'Component styling and section presentation',
                    ] as $item)
                        <li class="flex items-start gap-3">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-12 lg:mt-0">
                <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">What your church can customise</h2>
                <ul class="mt-6 space-y-3 text-sm text-ink/70">
                    @foreach ([
                        'Church name and logo',
                        'Approved brand colors',
                        'Images and copy',
                        'Buttons, links, and banners',
                        'Navigation and page content',
                    ] as $item)
                        <li class="flex items-start gap-3">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Section 06 — Responsive experience --}}
    <section class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
        <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Designed for every screen</h2>
        <p class="mt-4 text-base leading-relaxed text-ink/70">
            Like every Keryon theme, Proclaim is being designed mobile-first, so your church's website reads well on a phone during a Sunday announcement and on a desktop during planning.
        </p>
    </section>

    {{-- Section 07 — Availability status --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 lg:px-8">
            <x-site.theme-status status="preview" />
            <h2 class="mt-4 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Live demo coming soon</h2>
            <p class="mx-auto mt-4 max-w-[52ch] text-base leading-relaxed text-ink/70">
                Proclaim is currently in design and development. Activation isn't available yet — book a demo and we'll let you know as soon as Proclaim is ready to preview with your church's own content.
            </p>
        </div>
    </section>

    {{-- Section 08 — Custom design alternative --}}
    <section class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
        <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Want something built just for your church?</h2>
        <p class="mx-auto mt-4 max-w-[52ch] text-base leading-relaxed text-ink/70">
            If Proclaim isn't the right fit, Keryon also offers custom website design as a paid service — a private theme designed specifically around your church's identity.
        </p>
        <div class="mt-8">
            <a href="{{ route('site.themes.custom-design') }}" class="rounded-button border border-ink/15 px-6 py-3 text-sm font-semibold text-ink transition hover:bg-ink/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Request a Custom Design
            </a>
        </div>
    </section>

    {{-- Section 09 — Final CTA --}}
    <section class="bg-primary">
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <h2 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                Be the first to know when Proclaim is ready.
            </h2>
            <a href="{{ route('site.book-demo') }}" class="mt-8 inline-block rounded-button bg-white px-6 py-3 text-sm font-semibold text-primary transition hover:bg-white/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary">
                Book a Demo
            </a>
        </div>
    </section>
</x-layouts.site>
