@props(['status'])

{{-- Public, static status label for the theme catalogue — K-WEB-003 §12.
     No database-backed enum yet; this is marketing-site presentation only.
     status: 'available' | 'preview' | 'coming-soon' --}}
@php
    $config = match ($status) {
        'available' => ['label' => 'Available', 'class' => 'bg-primary/10 text-primary'],
        'preview' => ['label' => 'Preview', 'class' => 'bg-accent/10 text-ink/70'],
        default => ['label' => 'Coming Soon', 'class' => 'bg-ink/5 text-ink/60'],
    };
@endphp

<span class="inline-flex items-center rounded-button px-3 py-1 text-xs font-semibold {{ $config['class'] }}">
    {{ $config['label'] }}
</span>
