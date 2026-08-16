<x-layouts.site
    title="Custom Website Design — Keryon"
    description="Keryon's custom website design service designs and builds a private, church-specific theme — delivered as a Keryon-compatible website your team manages from the same communications workspace."
>
    {{-- Section 01 — Hero --}}
    <section class="mx-auto max-w-3xl px-4 pb-16 pt-16 text-center sm:px-6 lg:px-8 lg:pt-24">
        <p class="text-sm font-semibold uppercase tracking-wide text-accent">Custom Website Design</p>
        <h1 class="mt-4 text-4xl font-bold leading-tight tracking-tight text-ink sm:text-5xl">
            Built around your church. Powered by Keryon.
        </h1>
        <p class="mx-auto mt-6 max-w-[56ch] text-lg leading-relaxed text-ink/70">
            For churches that want a distinctive digital presence beyond the curated theme collection, Keryon can design and build a custom website experience as a paid service. Pricing is provided by custom quote.
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
            <a href="{{ route('site.book-demo') }}" class="rounded-button bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Discuss a Custom Website
            </a>
            <a href="{{ route('site.themes') }}" class="rounded-button border border-ink/15 px-6 py-3 text-sm font-semibold text-ink transition hover:bg-ink/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                See the Theme Collection
            </a>
        </div>
    </section>

    {{-- Section 02 — Process --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-4xl px-4 py-20 sm:px-6 lg:px-8">
            <h2 class="text-center text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                A considered process, not a form to fill out
            </h2>

            <ol class="mt-12 grid grid-cols-1 gap-x-8 gap-y-10 sm:grid-cols-2">
                @foreach ([
                    ['n' => '01', 'title' => 'Discover', 'body' => 'Understand the church, its communication needs, and its visual direction.'],
                    ['n' => '02', 'title' => 'Define', 'body' => 'Establish content structure, brand direction, and website requirements.'],
                    ['n' => '03', 'title' => 'Design', 'body' => 'Create a church-specific visual experience.'],
                    ['n' => '04', 'title' => 'Build', 'body' => 'Implement the approved design as a Keryon-compatible website theme.'],
                    ['n' => '05', 'title' => 'Launch', 'body' => 'Connect your Keryon-managed website content and prepare the site for launch.'],
                ] as $step)
                    <li class="border-t border-slate-200/80 pt-4">
                        <span class="text-sm font-semibold text-accent">{{ $step['n'] }}</span>
                        <h3 class="mt-2 text-base font-semibold text-ink">{{ $step['title'] }}</h3>
                        <p class="mt-1 text-sm leading-relaxed text-ink/70">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Section 03 — Outcome --}}
    <section class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
        <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
            Still one connected church website — not a separate system
        </h2>
        <p class="mx-auto mt-4 max-w-[56ch] text-base leading-relaxed text-ink/70">
            A custom design doesn't create a disconnected website with its own content system to maintain. It becomes a private theme built just for your church, connected to the same Keryon website content you already manage.
        </p>

        <div class="mt-10 flex flex-col items-center gap-2 text-sm font-medium text-ink/70">
            <span>Church Content in Keryon</span>
            <span class="text-ink/30" aria-hidden="true">+</span>
            <span>Private Custom Theme</span>
            <span class="text-ink/30" aria-hidden="true">↓</span>
            <span class="text-base font-semibold text-ink">Unique Church Website</span>
        </div>
    </section>

    {{-- Section 04 — Theme vs Custom comparison --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-4xl px-4 py-20 sm:px-6 lg:px-8">
            <h2 class="text-center text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                Curated design, or bespoke design
            </h2>

            <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div class="rounded-card border border-slate-200/80 bg-white p-8">
                    <h3 class="text-lg font-semibold text-ink">Keryon Theme</h3>
                    <p class="mt-2 text-sm font-medium text-ink/50">Best when:</p>
                    <p class="mt-1 text-sm leading-relaxed text-ink/70">
                        You want a professionally designed website ready to personalise with your church's identity and content.
                    </p>
                    <a href="{{ route('site.themes') }}" class="mt-6 inline-block rounded-button border border-ink/15 px-4 py-2 text-sm font-semibold text-ink transition hover:bg-ink/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                        See the Theme Collection
                    </a>
                </div>

                <div class="rounded-card border border-primary/20 bg-primary/5 p-8">
                    <h3 class="text-lg font-semibold text-ink">Custom Design</h3>
                    <p class="mt-2 text-sm font-medium text-ink/50">Best when:</p>
                    <p class="mt-1 text-sm leading-relaxed text-ink/70">
                        Your church needs a distinctive visual experience created around its own identity, beyond the curated collection.
                    </p>
                    <a href="{{ route('site.book-demo') }}" class="mt-6 inline-block rounded-button bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                        Discuss a Custom Website
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Section 05 — Final CTA --}}
    <section class="bg-primary">
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <h2 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                Let's talk about your church's website.
            </h2>
            <a href="{{ route('site.book-demo') }}" class="mt-8 inline-block rounded-button bg-white px-6 py-3 text-sm font-semibold text-primary transition hover:bg-white/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary">
                Discuss a Custom Website
            </a>
        </div>
    </section>
</x-layouts.site>
