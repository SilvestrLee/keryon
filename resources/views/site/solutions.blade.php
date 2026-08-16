<x-layouts.site
    title="Solutions — Keryon"
    description="See the real church communication problems Keryon helps solve — from knowing your congregation to keeping care visible, organizing content, running campaigns, and presenting it all through a professionally designed church website."
>
    {{-- Section 01 — Hero --}}
    <section class="mx-auto max-w-3xl px-4 pb-16 pt-16 text-center sm:px-6 lg:px-8 lg:pt-24">
        <p class="text-sm font-semibold uppercase tracking-wide text-accent">Keryon Solutions</p>
        <h1 class="mt-4 text-4xl font-bold leading-tight tracking-tight text-ink sm:text-5xl">
            Bring scattered church communication work into one focused system.
        </h1>
        <p class="mx-auto mt-6 max-w-[56ch] text-lg leading-relaxed text-ink/70">
            Church communication rarely stays in one place — it spans people, prayer and care, content, your website, and the seasons that matter most. Keryon brings those responsibilities into one communications workspace.
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
            <a href="{{ route('site.book-demo') }}" class="rounded-button bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Book a Demo
            </a>
            <a href="{{ route('site.features') }}" class="rounded-button border border-ink/15 px-6 py-3 text-sm font-semibold text-ink transition hover:bg-ink/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Explore Features
            </a>
        </div>
    </section>

    {{-- Section 02 — Problem Landscape --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-4xl px-4 py-20 sm:px-6 lg:px-8">
            <h2 class="text-center text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                Church communication rarely lives in one place
            </h2>
            <p class="mx-auto mt-4 max-w-[56ch] text-center text-base leading-relaxed text-ink/70">
                Church communication can become fragmented as responsibilities grow — spread across spreadsheets, group chats, and tools that were never built to work together.
            </p>

            <dl class="mt-12 grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                @foreach ([
                    'The member list lives in one place.',
                    'Prayer requests arrive somewhere else.',
                    'Website updates depend on another workflow.',
                    'Campaign planning starts again from scratch every season.',
                ] as $point)
                    <div class="flex items-start gap-3 border-t border-slate-200/80 pt-4">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>
                        <dt class="text-base font-medium text-ink">{{ $point }}</dt>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- Section 03 — Solution 01: Congregation --}}
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:grid lg:grid-cols-2 lg:items-start lg:gap-16 lg:px-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-accent">Solution 01</p>
            <h2 class="mt-3 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                Start every communication with a clearer picture of who you're reaching.
            </h2>
            <p class="mt-4 max-w-[48ch] text-base leading-relaxed text-ink/70">
                As a church grows, member and visitor information can become scattered, outdated, or hard to search. Congregation keeps that information organized in one place — active, visitor, and inactive records — so your team has real context before a message goes out.
            </p>
        </div>

        <div class="mt-12 lg:mt-1">
            <x-site.solution-flow
                problem="Member and visitor information becomes fragmented and hard to search as a church grows."
                brings="Congregation gives communication teams organized, searchable context on the people they're serving."
                easier="Communication can start with a clearer picture of who you're reaching."
            />
        </div>
    </section>

    {{-- Section 04 — Solution 02: Care Center --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:grid lg:grid-cols-2 lg:items-start lg:gap-16 lg:px-8">
            <div class="lg:order-2">
                <p class="text-sm font-semibold uppercase tracking-wide text-accent">Solution 02</p>
                <h2 class="mt-3 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                    Keep prayer and care needs visible instead of relying on memory.
                </h2>
                <p class="mt-4 max-w-[48ch] text-base leading-relaxed text-ink/70">
                    Prayer requests and care needs often arrive informally — a conversation after service, a message to one staff member, a note that's easy to lose. Care Center gives your team one place to capture and review them, see what needs attention, and keep follow-up context in view.
                </p>
            </div>

            <div class="mt-12 lg:order-1 lg:mt-1">
                <x-site.solution-flow
                    problem="Prayer requests and care needs arrive informally and can be hard to track consistently."
                    brings="Care Center gives the team one focused place to capture and review them."
                    easier="Care communication becomes more visible and less dependent on memory or scattered channels."
                />
            </div>
        </div>
    </section>

    {{-- Section 05 — Solution 03: Communications Hub (wide composition) --}}
    <section class="mx-auto max-w-4xl px-4 py-20 text-center sm:px-6 lg:px-8">
        <p class="text-sm font-semibold uppercase tracking-wide text-accent">Solution 03</p>
        <h2 class="mt-3 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
            Bring the church's content work into one place.
        </h2>
        <p class="mx-auto mt-4 max-w-[60ch] text-base leading-relaxed text-ink/70">
            Website updates, announcement copy, graphics, and campaign planning often end up scattered across files, chat threads, and unrelated tools. Communications Hub brings those responsibilities together, so your team works from one shared plan instead of piecing it together every week.
        </p>

        <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
            @foreach ([
                'Website Content',
                'Content Studio',
                'Content Calendar',
                'Templates',
                'Media Library',
                'Scheduled Posts',
            ] as $capability)
                <span class="rounded-button border border-slate-200/80 bg-white px-4 py-2 text-sm font-medium text-ink">
                    {{ $capability }}
                </span>
            @endforeach
        </div>

        <p class="mx-auto mt-8 max-w-[56ch] text-sm font-medium text-ink">
            That makes it easier to plan, prepare, and publish communication from one shared workspace instead of switching between disconnected tools.
        </p>
    </section>

    {{-- Section 06 — Website Experience Interlude --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-4xl px-4 py-20 sm:px-6 lg:px-8">
            <h2 class="text-center text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                Manage the content. Choose the experience.
            </h2>
            <p class="mx-auto mt-4 max-w-[56ch] text-center text-base leading-relaxed text-ink/70">
                Website content stays managed through Keryon. The theme you choose determines how that content is presented.
            </p>

            <div class="mt-12 flex flex-col items-center gap-3 text-sm font-medium text-ink/70">
                <span>Your church's content</span>
                <span class="text-ink/30" aria-hidden="true">↓</span>
                <span>Keryon Website Content</span>
                <span class="text-ink/30" aria-hidden="true">↓</span>
                <span class="text-xs font-semibold uppercase tracking-wide text-ink/40">Choose</span>

                <div class="mt-2 grid w-full grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-card border border-slate-200/80 bg-white p-6 text-left">
                        <h3 class="text-base font-semibold text-ink">Keryon Theme</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink/70">
                            Professionally designed Keryon website experiences that churches can personalise with supported content and branding.
                        </p>
                        <a href="{{ route('site.themes') }}" class="mt-4 inline-block text-sm font-medium text-primary underline decoration-primary/30 underline-offset-2 hover:decoration-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                            Explore Themes
                        </a>
                    </div>

                    <div class="rounded-card border border-slate-200/80 bg-white p-6 text-left">
                        <h3 class="text-base font-semibold text-ink">Custom Website Design</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink/70">
                            Paid bespoke design for churches that need a distinctive website, implemented as a Keryon-compatible custom theme.
                        </p>
                        <a href="{{ route('site.themes.custom-design') }}" class="mt-4 inline-block text-sm font-medium text-primary underline decoration-primary/30 underline-offset-2 hover:decoration-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                            Custom Website Design
                        </a>
                    </div>
                </div>

                <span class="mt-2 text-ink/30" aria-hidden="true">↓</span>
                <span class="text-base font-semibold text-ink">Church website</span>
            </div>
        </div>
    </section>

    {{-- Section 07 — Solution 04: Campaigns (timeline composition) --}}
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="text-center">
            <p class="text-sm font-semibold uppercase tracking-wide text-accent">Solution 04</p>
            <h2 class="mt-3 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                Give important church seasons more structure than a fresh start every time.
            </h2>
            <p class="mx-auto mt-4 max-w-[60ch] text-base leading-relaxed text-ink/70">
                Easter, Christmas, youth week, and other key moments bring a burst of communication work — website updates, announcements, graphics — that's easy to rebuild from scratch each time. Campaigns organizes that work around a single shared initiative, so your team plans once and works from it together.
            </p>
        </div>

        <div class="mt-12 flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
            @foreach (['Easter', 'Christmas', 'Youth Week', 'Pastor Appreciation'] as $index => $campaign)
                @if ($index > 0)
                    <div class="hidden h-px flex-1 bg-slate-200/80 sm:block" aria-hidden="true"></div>
                @endif
                <div class="flex items-center gap-3 sm:flex-col sm:gap-2 sm:text-center">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                    <span class="text-sm font-semibold text-ink">{{ $campaign }}</span>
                </div>
            @endforeach
        </div>

        <div class="mx-auto mt-12 max-w-2xl">
            <x-site.solution-flow
                problem="Important church seasons bring a burst of communication work that's easy to rebuild from scratch each time."
                brings="Campaigns organizes planning, content, and communication assets around one shared initiative."
                easier="Teams can approach big moments with structure instead of starting over."
            />
        </div>
    </section>

    {{-- Section 08 — One Communications Workspace synthesis --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-3xl px-4 py-20 sm:px-6 lg:px-8">
            <h2 class="text-center text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                Different responsibilities. One communications workspace.
            </h2>
            <p class="mx-auto mt-4 max-w-[56ch] text-center text-base leading-relaxed text-ink/70">
                Keryon doesn't stitch together separate products — it brings related communication responsibilities into one connected workspace.
            </p>

            <ol class="mx-auto mt-12 max-w-md space-y-6 border-l-2 border-slate-200/80 pl-6">
                @foreach ([
                    ['title' => 'Congregation', 'body' => 'know who needs communication'],
                    ['title' => 'Care Center', 'body' => 'see what needs attention'],
                    ['title' => 'Communications Hub', 'body' => 'prepare and organise content'],
                    ['title' => 'Campaigns', 'body' => 'coordinate important moments'],
                    ['title' => 'Website Experience', 'body' => "deliver the church's digital presence"],
                ] as $step)
                    <li>
                        <p class="text-sm font-semibold text-ink">{{ $step['title'] }}</p>
                        <p class="mt-1 text-sm text-ink/70">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Section 09 — Who Keryon Helps --}}
    <section class="mx-auto max-w-5xl px-4 py-20 sm:px-6 lg:px-8">
        <h2 class="text-center text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
            Who Keryon helps
        </h2>

        <div class="mt-12 grid grid-cols-1 gap-x-8 gap-y-8 sm:grid-cols-2">
            @foreach ([
                ['title' => 'Communications leaders', 'body' => 'Keep content and campaigns from being spread across unrelated workflows.'],
                ['title' => 'Church administrators', 'body' => 'Maintain clearer communication context around congregation records and church information.'],
                ['title' => 'Pastoral and care teams', 'body' => 'Keep prayer and care needs visible.'],
                ['title' => 'Ministry contributors', 'body' => 'Work from the same shared content plan as the rest of the team.'],
                ['title' => 'Small church teams', 'body' => 'Give one person carrying several communication responsibilities a more focused workspace.'],
            ] as $audience)
                <div class="border-t border-slate-200/80 pt-4">
                    <h3 class="text-base font-semibold text-ink">{{ $audience['title'] }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-ink/70">{{ $audience['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Section 10 — Final CTA --}}
    <section class="bg-primary">
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <h2 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                See how Keryon fits your church's communication work.
            </h2>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ route('site.book-demo') }}" class="rounded-button bg-white px-6 py-3 text-sm font-semibold text-primary transition hover:bg-white/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary">
                    Book a Demo
                </a>
                <a href="{{ route('site.features') }}" class="rounded-button border border-white/40 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary">
                    Explore Features
                </a>
            </div>
        </div>
    </section>
</x-layouts.site>
