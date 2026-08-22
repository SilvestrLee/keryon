{{--
    K-FAITHFLOW-001F-R2 — the reusable Keryon Celebration primitive.
    Circular confirmation + Deep Forest Teal fill + restrained outward
    Dawn Amber radiation, one-time entrance only (no looping). See §17 —
    reserved for a genuine milestone (every selected output settled),
    never per-field-edit or per-approval (those stay Level-2 toasts).

    Visibility is server-driven (the parent view wraps this include in
    `@if ($showCelebration)`) so the dismiss button calls the real
    `dismissCelebration()` Livewire method rather than local Alpine state —
    otherwise the banner could reappear on the next unrelated render, since
    Alpine's local state would reset while the PHP property stayed true.
--}}
<div class="ff-celebration relative flex items-start gap-4 p-6" role="status">
    <span class="ff-celebration-icon relative h-12 w-12 shrink-0">
        <span class="ff-celebration-ring" aria-hidden="true"></span>
        <span class="ff-celebration-ring ff-celebration-ring--delay" aria-hidden="true"></span>
        <x-filament::icon icon="heroicon-o-check" class="relative h-6 w-6" />
    </span>

    <div class="min-w-0 flex-1">
        <p class="text-base font-semibold">Your communication set is ready.</p>
        <p class="mt-1 text-sm" style="color: rgba(255,255,255,0.8)">
            Every output from this source has been generated and reviewed.
        </p>
    </div>

    <button
        type="button"
        wire:click="dismissCelebration"
        class="ff-focus shrink-0 rounded-full p-1"
        style="color: rgba(255,255,255,0.7)"
        aria-label="Dismiss"
    >
        <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
    </button>
</div>
