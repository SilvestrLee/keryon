<x-layouts.site
    title="Website Themes — Keryon"
    description="Choose a professionally designed Keryon website theme, or commission a custom design — both built on the same church content and communications workspace."
>
    {{-- Section 01 — Hero --}}
    <section class="mx-auto max-w-3xl px-4 pb-16 pt-16 text-center sm:px-6 lg:px-8 lg:pt-24">
        <p class="text-sm font-semibold uppercase tracking-wide text-accent">Keryon Website Themes</p>
        <h1 class="mt-4 text-4xl font-bold leading-tight tracking-tight text-ink sm:text-5xl">
            A church website should feel like your church — not like a template.
        </h1>
        <p class="mx-auto mt-6 max-w-[56ch] text-lg leading-relaxed text-ink/70">
            Choose a professionally designed Keryon theme, add your church's identity and content, and manage the website from the same communications workspace you already use.
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
            <a href="#collection" class="rounded-button bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Explore Themes
            </a>
            <a href="{{ route('site.themes.custom-design') }}" class="rounded-button border border-ink/15 px-6 py-3 text-sm font-semibold text-ink transition hover:bg-ink/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Request a Custom Design
            </a>
        </div>
    </section>

    {{-- Section 02 — Curated collection positioning --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 lg:px-8">
            <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                Professionally designed. Church-ready. Built to be made yours.
            </h2>
            <p class="mt-4 text-base leading-relaxed text-ink/70">
                Keryon offers a deliberately curated collection of themes rather than an endless template library. Every theme is designed to hold your church's identity well — your logo, your colors, your content — while keeping the layout coherent and considered.
            </p>
        </div>
    </section>

    {{-- Section 03 — Collection: Proclaim --}}
    <section id="collection" class="mx-auto max-w-5xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-card border border-slate-200/80">
            <x-site.interface-preview
                label="Proclaim"
                tint="primary"
                aspect="wide"
                caption="Proclaim's live preview is still in design and development."
            />
            <div class="p-8 sm:p-10">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Proclaim</h2>
                    <x-site.theme-status status="preview" />
                </div>
                <p class="mt-3 max-w-[60ch] text-base leading-relaxed text-ink/70">
                    Bold. Editorial. Message-led. A contemporary church website experience designed for churches that want preaching, worship, and community to lead their digital presence.
                </p>
                <div class="mt-6">
                    <a href="{{ route('site.themes.proclaim') }}" class="rounded-button bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                        Explore Theme
                    </a>
                </div>
            </div>
        </div>

        <p class="mt-6 text-center text-sm text-ink/50">
            Proclaim is the first Keryon theme. More themes are in development.
        </p>
    </section>

    {{-- Section 04 — Custom design promotion --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <p class="text-sm font-semibold uppercase tracking-wide text-accent">Custom Website Design</p>
            <h2 class="mt-3 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                Want something entirely your own?
            </h2>
            <p class="mx-auto mt-4 max-w-[52ch] text-base leading-relaxed text-ink/70">
                For churches that want a distinctive digital presence beyond the curated collection, Keryon can design and build a custom website experience as a paid service. Pricing is provided by custom quote.
            </p>
            <div class="mt-8">
                <a href="{{ route('site.themes.custom-design') }}" class="rounded-button bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                    Request a Custom Design
                </a>
            </div>
        </div>
    </section>

    {{-- Section 05 — Final CTA --}}
    <section class="bg-primary">
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <h2 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                Ready to see a Keryon website built for your church?
            </h2>
            <a href="{{ route('site.book-demo') }}" class="mt-8 inline-block rounded-button bg-white px-6 py-3 text-sm font-semibold text-primary transition hover:bg-white/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary">
                Book a Demo
            </a>
        </div>
    </section>
</x-layouts.site>
