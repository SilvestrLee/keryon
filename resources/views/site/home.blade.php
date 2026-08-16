<x-layouts.site>
    {{-- Section 01 — Hero --}}
    <section class="mx-auto max-w-7xl px-4 pb-16 pt-16 sm:px-6 lg:grid lg:grid-cols-2 lg:items-center lg:gap-16 lg:px-8 lg:pt-24">
        <div class="motion-safe:animate-[fade-in-up_0.5s_ease-out]">
            <h1 class="text-4xl font-bold leading-tight tracking-tight text-ink sm:text-5xl">
                Your Church's Digital Communications Staff Member.
            </h1>
            <p class="mt-6 max-w-[46ch] text-lg leading-relaxed text-ink/70">
                Help your church communicate clearly, care intentionally, and keep everyone informed, without the complexity of traditional church software.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-4">
                <a href="{{ route('site.book-demo') }}" class="rounded-button bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                    Book a Demo
                </a>
                <a href="#what-keryon-does" class="rounded-button border border-ink/15 px-6 py-3 text-sm font-semibold text-ink transition hover:bg-ink/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                    See How It Works
                </a>
            </div>
        </div>

        <div class="mt-12 lg:mt-0">
            {{-- TODO: replace with a real Keryon dashboard screenshot (approx. 1600x1200) once the product UI exists to capture --}}
            <div class="flex aspect-[4/3] items-center justify-center rounded-card border border-dashed border-ink/15 bg-white/60 text-center">
                <p class="max-w-[24ch] text-sm text-ink/40">Product screenshot placeholder — dashboard preview coming soon</p>
            </div>
        </div>
    </section>

    {{-- Section 02 — The Problem --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                When communication is scattered, people fall through the cracks.
            </h2>
            <p class="mt-4 text-base leading-relaxed text-ink/70">
                Church communication often becomes fragmented, reactive, and dependent on too many tools.
            </p>
        </div>
    </section>

    {{-- Section 03 — What Keryon Does --}}
    <section id="what-keryon-does" class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <h2 class="text-center text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
            What Keryon does
        </h2>

        <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2">
            @foreach ([
                ['title' => 'Congregation', 'body' => 'Know and understand your church community.', 'tint' => false],
                ['title' => 'Care Center', 'body' => 'Prayer requests and intentional care.', 'tint' => true],
                ['title' => 'Communications Hub', 'body' => 'Announcements and communication workflows.', 'tint' => true],
                ['title' => 'Campaigns', 'body' => 'Coordinate initiatives and church engagement.', 'tint' => false],
            ] as $card)
                <div @class([
                    'rounded-card border border-slate-200/80 p-8',
                    'bg-primary/5' => $card['tint'],
                    'bg-white' => ! $card['tint'],
                ])>
                    <h3 class="text-lg font-semibold text-ink">{{ $card['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink/70">{{ $card['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Section 04 — Product Showcase --}}
    <section class="border-y border-slate-200/80 bg-white/50">
        <div class="mx-auto max-w-5xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                See Keryon in action
            </h2>
            {{-- TODO: replace with real product screenshots (dashboard, Care Center, Communications Hub) --}}
            <div class="mt-10 flex aspect-[16/9] items-center justify-center rounded-card border border-dashed border-ink/15 bg-white text-center">
                <p class="max-w-[28ch] text-sm text-ink/40">Product showcase placeholder — screenshots coming soon</p>
            </div>
        </div>
    </section>

    {{-- Section 05 — Why Churches Choose Keryon --}}
    <section class="mx-auto max-w-4xl px-4 py-20 sm:px-6 lg:px-8">
        <h2 class="text-center text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
            Why churches choose Keryon
        </h2>

        <dl class="mt-12 grid grid-cols-1 gap-x-8 gap-y-8 sm:grid-cols-2">
            @foreach ([
                'Simpler than a Church ERP',
                'Built for communication',
                'Easy to adopt',
                'Designed for ministry teams',
            ] as $reason)
                <div class="flex items-start gap-3 border-t border-slate-200/80 pt-4">
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-accent"></span>
                    <dt class="text-base font-medium text-ink">{{ $reason }}</dt>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Section 07 — Final CTA --}}
    <section class="bg-primary">
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <h2 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                Ready to serve your church better?
            </h2>
            <a href="{{ route('site.book-demo') }}" class="mt-8 inline-block rounded-button bg-white px-6 py-3 text-sm font-semibold text-primary transition hover:bg-white/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary">
                Book a Demo
            </a>
        </div>
    </section>
</x-layouts.site>
