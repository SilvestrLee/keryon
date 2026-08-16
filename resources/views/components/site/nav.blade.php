@php
    $navLinks = [
        ['label' => 'Features', 'route' => 'site.features'],
        ['label' => 'Solutions', 'route' => 'site.solutions'],
        ['label' => 'Themes', 'route' => 'site.themes'],
        ['label' => 'Pricing', 'route' => 'site.pricing'],
        ['label' => 'Resources', 'route' => 'site.resources'],
        ['label' => 'About', 'route' => 'site.about'],
    ];
@endphp

<header class="sticky top-0 z-30 border-b border-slate-200/80 bg-surface/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="rounded-button text-lg font-semibold tracking-tight text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
            Keryon
        </a>

        <nav class="hidden items-center gap-8 lg:flex" aria-label="Primary">
            @foreach ($navLinks as $link)
                <a href="{{ route($link['route']) }}" class="rounded-button text-sm font-medium text-ink/70 transition hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-3 lg:flex">
            <a href="{{ route('filament.admin.auth.login') }}" class="rounded-button text-sm font-medium text-ink/70 transition hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Login
            </a>
            <a href="{{ route('site.book-demo') }}" class="rounded-button bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Book a Demo
            </a>
        </div>

        <button
            type="button"
            class="inline-flex items-center justify-center rounded-button p-2 text-ink lg:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
            aria-label="Toggle navigation menu"
            aria-expanded="false"
            :aria-expanded="mobileNavOpen.toString()"
            @click="mobileNavOpen = !mobileNavOpen"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path x-show="!mobileNavOpen" stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                <path x-show="mobileNavOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div
        x-show="mobileNavOpen"
        x-cloak
        x-transition:enter="motion-safe:transition motion-safe:ease-out motion-safe:duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="border-t border-slate-200/80 bg-surface lg:hidden"
    >
        <nav class="flex flex-col gap-1 px-4 py-4" aria-label="Mobile">
            @foreach ($navLinks as $link)
                <a href="{{ route($link['route']) }}" class="rounded-button px-3 py-2 text-sm font-medium text-ink/80 hover:bg-ink/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                    {{ $link['label'] }}
                </a>
            @endforeach
            <a href="{{ route('filament.admin.auth.login') }}" class="rounded-button px-3 py-2 text-sm font-medium text-ink/80 hover:bg-ink/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                Login
            </a>
        </nav>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/80 bg-surface/95 p-3 backdrop-blur lg:hidden">
        <a href="{{ route('site.book-demo') }}" class="block rounded-button bg-primary px-4 py-3 text-center text-sm font-semibold text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
            Book a Demo
        </a>
    </div>
</header>
