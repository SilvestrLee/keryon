@props(['label', 'tint' => 'primary', 'aspect' => 'standard', 'caption' => 'Real Keryon product imagery will appear here.'])

{{-- Honest visual placeholder. Used for both product-interface previews
     (K-WEB-002 §8) and website-theme previews (K-WEB-003 §26) — no real
     screenshot exists yet in either case. This is a deliberate framing
     device (mock browser chrome + labelled empty state), not a fabricated
     screenshot. Reused rather than duplicated per K-WEB-003 §32. --}}
<div class="overflow-hidden rounded-card border border-slate-200/80 bg-white">
    <div class="flex items-center gap-1.5 border-b border-slate-200/80 bg-surface px-4 py-3" aria-hidden="true">
        <span @class([
            'h-2.5 w-2.5 rounded-full',
            'bg-primary/30' => $tint === 'primary',
            'bg-accent/40' => $tint === 'accent',
        ])></span>
        <span class="h-2.5 w-2.5 rounded-full bg-ink/10"></span>
        <span class="h-2.5 w-2.5 rounded-full bg-ink/10"></span>
    </div>
    <div @class([
        'flex flex-col items-center justify-center gap-1 px-6 text-center',
        'aspect-[4/3]' => $aspect === 'standard',
        'aspect-video' => $aspect === 'wide',
    ])>
        <p class="text-sm font-medium text-ink/50">{{ $label }} preview</p>
        <p class="max-w-[32ch] text-xs text-ink/40">{{ $caption }}</p>
    </div>
</div>
