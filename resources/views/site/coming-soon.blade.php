<x-layouts.site :title="$pageTitle . ' — Keryon'">
    <section class="mx-auto flex min-h-[60vh] max-w-2xl flex-col items-center justify-center px-4 py-24 text-center sm:px-6 lg:px-8">
        <h1 class="text-3xl font-semibold tracking-tight text-ink">{{ $pageTitle }}</h1>
        <p class="mt-4 text-base leading-relaxed text-ink/70">
            This page is on its way. In the meantime, book a demo and we'll walk you through it directly.
        </p>
        <a href="{{ route('site.book-demo') }}" class="mt-8 rounded-button bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
            Book a Demo
        </a>
    </section>
</x-layouts.site>
