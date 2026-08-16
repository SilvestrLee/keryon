@props(['label', 'tint' => 'primary'])

{{-- Honest product-visual placeholder. No real Keryon interface exists to
     capture yet — see K-WEB-002 §8. This is a deliberate framing device
     (mock browser chrome + labelled empty state), not a fabricated screenshot. --}}
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
    <div class="flex aspect-[4/3] flex-col items-center justify-center gap-1 px-6 text-center">
        <p class="text-sm font-medium text-ink/50">{{ $label }} preview</p>
        <p class="max-w-[26ch] text-xs text-ink/40">Real Keryon product imagery will appear here.</p>
    </div>
</div>
