<x-layouts.site
    title="Pricing — Keryon"
    description="One Keryon subscription covers the whole church communications workspace — Congregation, Care Center, Communications Hub, Campaigns, website content, and standard themes. Priced for global and Nigerian churches."
>
    {{-- Section 01 — Hero --}}
    <section class="mx-auto max-w-3xl px-4 pb-16 pt-16 text-center sm:px-6 lg:px-8 lg:pt-24">
        <p class="text-sm font-semibold uppercase tracking-wide text-accent">Keryon Pricing</p>
        <h1 class="mt-4 text-4xl font-bold leading-tight tracking-tight text-ink sm:text-5xl">
            One Keryon subscription. The whole communications workspace.
        </h1>
        <p class="mx-auto mt-6 max-w-[52ch] text-lg leading-relaxed text-ink/70">
            No module maze. No paying more because your church has more people to care for.
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
            <a href="{{ route('site.book-demo') }}" class="rounded-button bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Book a Demo
            </a>
            <a href="#pricing" class="rounded-button border border-ink/15 px-6 py-3 text-sm font-semibold text-ink transition hover:bg-ink/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                See the Price
            </a>
        </div>
    </section>

    {{-- Section 02 — One subscription, region-aware price --}}
    <section id="pricing" class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-2xl px-4 py-20 sm:px-6 lg:px-8" x-data="{ region: 'global' }">
            <div class="flex justify-center">
                <fieldset class="inline-flex gap-1 rounded-button border border-slate-200/80 bg-white p-1">
                    <legend class="sr-only">Choose your region</legend>

                    <label class="relative cursor-pointer rounded-button px-4 py-2 text-sm font-medium transition" :class="region === 'global' ? 'bg-primary text-white' : 'text-ink/70 hover:text-ink'">
                        <input type="radio" name="pricing-region" value="global" x-model="region" class="peer sr-only">
                        <span class="pointer-events-none absolute inset-0 rounded-button peer-focus-visible:ring-2 peer-focus-visible:ring-primary peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-surface"></span>
                        Global
                    </label>

                    <label class="relative cursor-pointer rounded-button px-4 py-2 text-sm font-medium transition" :class="region === 'nigeria' ? 'bg-primary text-white' : 'text-ink/70 hover:text-ink'">
                        <input type="radio" name="pricing-region" value="nigeria" x-model="region" class="peer sr-only">
                        <span class="pointer-events-none absolute inset-0 rounded-button peer-focus-visible:ring-2 peer-focus-visible:ring-primary peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-surface"></span>
                        Nigeria
                    </label>
                </fieldset>
            </div>

            <div class="mt-10 rounded-card border border-slate-200/80 bg-white p-8 text-center sm:p-10">
                <p class="text-lg font-semibold text-ink">Keryon</p>

                <div x-show="region === 'global'">
                    <p class="mt-4 text-5xl font-bold tracking-tight text-ink">
                        $59<span class="text-lg font-medium text-ink/50">/month</span>
                    </p>
                    <p class="mt-2 text-sm text-ink/60">
                        or $590/year — <span class="font-medium text-primary">save 2 months</span>
                    </p>
                </div>

                <div x-show="region === 'nigeria'" x-cloak>
                    <p class="mt-4 text-5xl font-bold tracking-tight text-ink">
                        ₦25,000<span class="text-lg font-medium text-ink/50">/month</span>
                    </p>
                    <p class="mt-2 text-sm text-ink/60">
                        or ₦250,000/year — <span class="font-medium text-primary">save 2 months</span>
                    </p>
                    <p class="mt-3 text-xs text-ink/40">Nigerian pricing is set locally, not converted from the global price.</p>
                </div>

                <p class="mx-auto mt-6 max-w-[36ch] text-sm leading-relaxed text-ink/70">
                    The complete church communications workspace. One price. Everyone on the team.
                </p>

                <a href="{{ route('site.book-demo') }}" class="mt-8 inline-block rounded-button bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                    Book a Demo
                </a>

                <p class="mt-4 text-xs text-ink/40">Applicable taxes may apply.</p>
            </div>
        </div>
    </section>

    {{-- Section 03 — What's included --}}
    <section class="mx-auto max-w-5xl px-4 py-20 sm:px-6 lg:px-8">
        <h2 class="text-center text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
            What's included
        </h2>

        <div class="mt-12 grid grid-cols-1 gap-x-8 gap-y-8 sm:grid-cols-2">
            @foreach ([
                ['title' => 'Congregation', 'body' => 'Know who the church is communicating with.'],
                ['title' => 'Care Center', 'body' => 'Keep prayer and care needs visible.'],
                ['title' => 'Communications Hub', 'body' => 'Coordinate website and content work.'],
                ['title' => 'Campaigns', 'body' => 'Organize important communication initiatives.'],
                ['title' => 'Website Content', 'body' => 'Manage supported church website content through Keryon.'],
                ['title' => 'Standard Website Themes', 'body' => 'Professionally designed Keryon themes included.'],
                ['title' => 'Managed Website Hosting', 'body' => 'Hosting for the Keryon-powered church website included.'],
                ['title' => 'Team Collaboration', 'body' => 'Included — bring your whole communications team.'],
                ['title' => 'Media Storage', 'body' => 'Generous media storage included.'],
            ] as $item)
                <div class="flex items-start gap-3 border-t border-slate-200/80 pt-4">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                    <div>
                        <p class="text-base font-semibold text-ink">{{ $item['title'] }}</p>
                        <p class="mt-1 text-sm leading-relaxed text-ink/70">{{ $item['body'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="mt-10 text-center text-sm text-ink/60">
            <a href="{{ route('site.features') }}" class="font-medium text-primary underline decoration-primary/30 underline-offset-2 hover:decoration-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                See all features
            </a>
        </p>
    </section>

    {{-- Section 04 — Trial --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-2xl px-4 py-16 text-center sm:px-6 lg:px-8">
            <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                Try Keryon for 21 days. No card required.
            </h2>
            <p class="mx-auto mt-4 max-w-[48ch] text-base leading-relaxed text-ink/70">
                Trial access is arranged as part of onboarding — book a demo and we'll set your church up with full access to explore Keryon before you decide.
            </p>
            <div class="mt-8">
                <a href="{{ route('site.book-demo') }}" class="inline-block rounded-button bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                    Book a Demo
                </a>
            </div>
        </div>
    </section>

    {{-- Section 05 — Website Themes --}}
    <section class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
        <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
            Your website design is part of Keryon.
        </h2>
        <p class="mx-auto mt-4 max-w-[52ch] text-base leading-relaxed text-ink/70">
            Choose from standard Keryon themes at no additional subscription cost — add your church's identity and content, and manage it all from the same workspace.
        </p>
        <div class="mt-8">
            <a href="{{ route('site.themes') }}" class="rounded-button border border-ink/15 px-6 py-3 text-sm font-semibold text-ink transition hover:bg-ink/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Explore Themes
            </a>
        </div>
    </section>

    {{-- Section 06 — Custom Website Design --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <p class="text-sm font-semibold uppercase tracking-wide text-accent">Custom Website Design</p>
            <h2 class="mt-3 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                Want something designed around your church?
            </h2>
            <p class="mx-auto mt-4 max-w-[52ch] text-base leading-relaxed text-ink/70">
                Keryon offers paid Custom Website Design for churches that want a bespoke digital experience — a one-time design and build fee, on top of your normal Keryon subscription. Custom quote.
            </p>
            <div class="mt-8">
                <a href="{{ route('site.themes.custom-design') }}" class="rounded-button bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                    Explore Custom Design
                </a>
            </div>
        </div>
    </section>

    {{-- Section 07 — FAQ --}}
    <section class="mx-auto max-w-3xl px-4 py-20 sm:px-6 lg:px-8">
        <h2 class="text-center text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
            Pricing FAQ
        </h2>

        <div class="mt-10 divide-y divide-slate-200/80 border-t border-slate-200/80">
            @foreach ([
                ['q' => 'What is included in Keryon?', 'a' => 'One complete communications workspace — Congregation, Care Center, Communications Hub, Campaigns, website content, and standard themes.'],
                ['q' => 'Are website themes included?', 'a' => 'Yes, standard Keryon themes are included in the subscription at no extra cost.'],
                ['q' => 'Can we request a custom website?', 'a' => 'Yes. Custom Website Design is a separate paid, custom-quote service — your regular Keryon subscription still applies alongside it.'],
                ['q' => 'Is hosting included?', 'a' => 'Managed hosting for your Keryon-powered church website is included.'],
                ['q' => 'Can we pay annually?', 'a' => 'Yes. Annual billing saves two months compared to paying monthly.'],
                ['q' => 'Can we try Keryon first?', 'a' => "Yes — Keryon's commercial policy is a 21-day full-featured trial with no card required. Book a demo to get started."],
                ['q' => 'Does pricing increase with congregation size?', 'a' => "No. Your subscription isn't priced by the number of people in your congregation."],
                ['q' => 'Is Nigeria pricing just converted from dollars?', 'a' => 'No. Nigerian pricing is set locally rather than automatically converted from USD.'],
            ] as $faq)
                <details class="group py-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-button text-left text-base font-medium text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                        {{ $faq['q'] }}
                        <svg class="h-5 w-5 shrink-0 text-ink/40 transition group-open:rotate-45" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-ink/70">{{ $faq['a'] }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- Section 08 — Final CTA --}}
    <section class="bg-primary">
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <h2 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                See Keryon's one subscription for your church.
            </h2>
            <a href="{{ route('site.book-demo') }}" class="mt-8 inline-block rounded-button bg-white px-6 py-3 text-sm font-semibold text-primary transition hover:bg-white/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary">
                Book a Demo
            </a>
        </div>
    </section>
</x-layouts.site>
