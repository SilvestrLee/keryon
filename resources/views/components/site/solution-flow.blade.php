@props(['problem', 'brings', 'easier'])

{{-- Compact problem → what Keryon brings together → what becomes easier
     rail — K-WEB-004 §14. All text is real content (not decorative), so
     nothing here is aria-hidden; the left border is a plain divider. --}}
<div class="space-y-4 border-l-2 border-slate-200/80 pl-5">
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">The problem</p>
        <p class="mt-1 text-sm leading-relaxed text-ink/70">{{ $problem }}</p>
    </div>
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">What Keryon brings together</p>
        <p class="mt-1 text-sm leading-relaxed text-ink/70">{{ $brings }}</p>
    </div>
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-accent">What becomes easier</p>
        <p class="mt-1 text-sm font-medium leading-relaxed text-ink">{{ $easier }}</p>
    </div>
</div>
