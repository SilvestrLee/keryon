<footer class="border-t border-slate-200/80 pb-24 pt-12 lg:pb-12">
    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <div>
            <p class="text-base font-semibold text-primary">Keryon</p>
            <p class="mt-1 text-sm text-ink/60">Your church's digital communications staff member.</p>
        </div>

        <nav class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-ink/70" aria-label="Footer">
            <a href="{{ route('site.features') }}" class="rounded-button hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">Features</a>
            <a href="{{ route('site.pricing') }}" class="rounded-button hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">Pricing</a>
            <a href="{{ route('site.about') }}" class="rounded-button hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">About</a>
            <a href="{{ route('site.book-demo') }}" class="rounded-button hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface">Book a Demo</a>
        </nav>
    </div>

    <p class="mx-auto mt-8 max-w-7xl px-4 text-xs text-ink/40 sm:px-6 lg:px-8">
        &copy; {{ now()->year }} Keryon. All rights reserved.
    </p>
</footer>
