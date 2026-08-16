<x-layouts.site
    title="Features — Keryon"
    description="See how Keryon helps your church's communications team know its people, care for them, reach them, and stay consistent online — all in one focused workspace."
>
    {{-- Section 01 — Hero --}}
    <section class="mx-auto max-w-3xl px-4 pb-16 pt-16 text-center sm:px-6 lg:px-8 lg:pt-24">
        <p class="text-sm font-semibold uppercase tracking-wide text-accent">Keryon Features</p>
        <h1 class="mt-4 text-4xl font-bold leading-tight tracking-tight text-ink sm:text-5xl">
            Keryon brings the church's communication work into one focused place.
        </h1>
        <p class="mx-auto mt-6 max-w-[56ch] text-lg leading-relaxed text-ink/70">
            Your communications team can keep track of the congregation, respond to care needs, prepare content, and coordinate campaigns — without stitching together spreadsheets, group chats, and disconnected tools.
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
            <a href="{{ route('site.book-demo') }}" class="rounded-button bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Book a Demo
            </a>
            <a href="#pillars" class="rounded-button border border-ink/15 px-6 py-3 text-sm font-semibold text-ink transition hover:bg-ink/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Explore the Four Pillars
            </a>
        </div>
    </section>

    {{-- Section 02 — Four Pillar Overview --}}
    <section id="pillars" class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <h2 class="text-center text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                Four pillars, one communications workspace
            </h2>

            <ol class="mt-12 grid grid-cols-1 gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['n' => '01', 'title' => 'Congregation', 'body' => 'Know who you\'re communicating with.'],
                    ['n' => '02', 'title' => 'Care Center', 'body' => 'Respond to prayer and care needs.'],
                    ['n' => '03', 'title' => 'Communications Hub', 'body' => 'Create and coordinate every message.'],
                    ['n' => '04', 'title' => 'Campaigns', 'body' => 'Rally communication around key moments.'],
                ] as $pillar)
                    <li class="border-t border-slate-200/80 pt-4">
                        <span class="text-sm font-semibold text-accent">{{ $pillar['n'] }}</span>
                        <h3 class="mt-2 text-base font-semibold text-ink">{{ $pillar['title'] }}</h3>
                        <p class="mt-1 text-sm leading-relaxed text-ink/70">{{ $pillar['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Section 03 — Congregation deep dive --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:grid lg:grid-cols-2 lg:items-center lg:gap-16 lg:px-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-accent">Congregation</p>
            <h2 class="mt-3 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                Know who the church is communicating with.
            </h2>
            <p class="mt-4 max-w-[48ch] text-base leading-relaxed text-ink/70">
                Keryon keeps congregation records organised and up to date, so communication is never sent into the dark. See who's active, who's visiting, and who's gone quiet — using statuses built for ministry, not a sales pipeline.
            </p>
            <ul class="mt-6 space-y-3 text-sm text-ink/70">
                @foreach ([
                    'Member profiles with contact and church information',
                    'Active, visitor, and inactive status at a glance',
                    'Search and filters built for pastoral context, not spreadsheets',
                ] as $item)
                    <li class="flex items-start gap-3">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-12 lg:mt-0">
            <x-site.interface-preview label="Congregation" tint="primary" />
        </div>
    </section>

    {{-- Section 04 — Care Center deep dive --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:grid lg:grid-cols-2 lg:items-center lg:gap-16 lg:px-8">
            <div class="lg:order-2">
                <p class="text-sm font-semibold uppercase tracking-wide text-accent">Care Center</p>
                <h2 class="mt-3 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                    Help care needs stay visible instead of scattered.
                </h2>
                <p class="mt-4 max-w-[48ch] text-base leading-relaxed text-ink/70">
                    Prayer requests shouldn't disappear into a group chat. Care Center gives your team one calm place to receive, review, and follow up on prayer and care needs — so nobody is forgotten.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-ink/70">
                    @foreach ([
                        'Prayer requests received in one place',
                        'Needs Attention, Currently Praying, and Recently Submitted views',
                        'Care notes and follow-up reminders for pastoral teams',
                    ] as $item)
                        <li class="flex items-start gap-3">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-12 lg:order-1 lg:mt-0">
                <x-site.interface-preview label="Care Center" tint="accent" />
            </div>
        </div>
    </section>

    {{-- Section 05 — Communications Hub deep dive --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:grid lg:grid-cols-2 lg:items-center lg:gap-16 lg:px-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-accent">Communications Hub</p>
            <h2 class="mt-3 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                Create and coordinate the church's communication in one workspace.
            </h2>
            <p class="mt-4 max-w-[48ch] text-base leading-relaxed text-ink/70">
                The Communications Hub brings website content, announcements, and scheduled messages together, so your team can prepare communication without switching between disconnected tools.
            </p>
        </div>

        <div class="mt-12 lg:mt-0">
            <div class="grid grid-cols-2 gap-3">
                @foreach ([
                    'Website Content',
                    'Content Studio',
                    'Content Calendar',
                    'Templates',
                    'Media Library',
                    'Scheduled Posts',
                ] as $feature)
                    <div class="rounded-card border border-slate-200/80 bg-white px-4 py-4">
                        <p class="text-sm font-medium text-ink">{{ $feature }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Section 06 — Campaigns deep dive --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:grid lg:grid-cols-2 lg:items-center lg:gap-16 lg:px-8">
            <div class="lg:order-2">
                <p class="text-sm font-semibold uppercase tracking-wide text-accent">Campaigns</p>
                <h2 class="mt-3 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                    Coordinate communication around the moments that matter.
                </h2>
                <p class="mt-4 max-w-[48ch] text-base leading-relaxed text-ink/70">
                    Easter, Christmas, and youth week all bring a burst of communication — website updates, announcements, graphics. Campaigns keep that work organised around a single communication objective instead of scattered across sticky notes.
                </p>
            </div>

            <div class="mt-12 lg:order-1 lg:mt-0">
                <div class="flex flex-wrap gap-3" role="list" aria-label="Example campaigns">
                    @foreach (['Easter', 'Christmas', 'Youth Week', 'Pastor Appreciation'] as $campaign)
                        <span role="listitem" class="rounded-button border border-primary/20 bg-primary/5 px-4 py-2 text-sm font-medium text-primary">
                            {{ $campaign }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Section 07 — Connected workflow --}}
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8">
        <h2 class="text-center text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
            One workspace, not four disconnected tools
        </h2>
        <p class="mx-auto mt-4 max-w-[56ch] text-center text-base leading-relaxed text-ink/70">
            Keryon keeps these responsibilities in one communications workspace, so your team moves from knowing your people to reaching them without losing context along the way.
        </p>

        <div class="mt-12 flex flex-col items-stretch gap-3 lg:flex-row lg:items-center lg:gap-4">
            @foreach ([
                ['title' => 'Congregation', 'body' => 'Understand who you\'re communicating with.'],
                ['title' => 'Care', 'body' => 'Understand what needs attention.'],
                ['title' => 'Communications', 'body' => 'Prepare the right message.'],
                ['title' => 'Campaigns', 'body' => 'Coordinate around key moments.'],
            ] as $index => $step)
                @if ($index > 0)
                    <div class="flex items-center justify-center text-ink/30" aria-hidden="true">
                        <svg class="hidden h-5 w-5 lg:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                        <svg class="block h-5 w-5 lg:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M8 17l4 4m0 0l4-4m-4 4V3" /></svg>
                    </div>
                @endif
                <div class="flex-1 rounded-card border border-slate-200/80 bg-white p-6 text-center">
                    <h3 class="text-base font-semibold text-ink">{{ $step['title'] }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-ink/70">{{ $step['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Section 08 — Final CTA --}}
    <section class="bg-primary">
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <h2 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                See these four pillars working together for your church.
            </h2>
            <a href="{{ route('site.book-demo') }}" class="mt-8 inline-block rounded-button bg-white px-6 py-3 text-sm font-semibold text-primary transition hover:bg-white/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary">
                Book a Demo
            </a>
        </div>
    </section>
</x-layouts.site>
