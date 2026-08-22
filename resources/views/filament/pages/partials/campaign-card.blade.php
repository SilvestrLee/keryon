@php
    $readiness = $this->readinessCounts($campaign);
    $nearestTarget = $this->nearestTarget($campaign);
@endphp

<a
    href="{{ \App\Filament\Pages\CampaignWorkspace::getUrl(['campaign' => $campaign->id]) }}"
    wire:navigate
    class="group flex min-h-52 flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-emerald-200 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h3 class="truncate text-base font-semibold text-gray-950 group-hover:text-[#1E5631]">{{ $campaign->title }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ $this->periodLabel($campaign) }}</p>
        </div>
        <x-filament::badge :color="$this->statusColor($campaign->status)">{{ $campaign->status->label() }}</x-filament::badge>
    </div>

    @if ($campaign->purpose)
        <p class="mt-4 line-clamp-2 text-sm leading-6 text-gray-600">{{ $campaign->purpose }}</p>
    @endif

    <div class="mt-auto pt-5">
        <div class="flex items-center justify-between gap-3 text-sm">
            <span class="font-medium text-gray-700">{{ $readiness['total'] }} planned</span>
            <span class="text-gray-500">{{ $readiness['prepared'] }} ready</span>
        </div>
        <div class="mt-2 flex h-1.5 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
            @if ($readiness['total'] > 0)
                <span class="bg-emerald-600" style="width: {{ ($readiness['prepared'] / $readiness['total']) * 100 }}%"></span>
                <span class="bg-amber-400" style="width: {{ ($readiness['awaiting_approval'] / $readiness['total']) * 100 }}%"></span>
                <span class="bg-blue-400" style="width: {{ ($readiness['in_preparation'] / $readiness['total']) * 100 }}%"></span>
            @endif
        </div>
        <p class="sr-only">
            {{ $readiness['prepared'] }} ready, {{ $readiness['awaiting_approval'] }} awaiting approval,
            {{ $readiness['in_preparation'] }} in preparation, and {{ $readiness['not_started'] + $readiness['outstanding'] }} not started or outstanding.
        </p>

        @if ($nearestTarget)
            <p class="mt-3 flex items-center gap-2 text-xs text-gray-500">
                <x-filament::icon icon="heroicon-o-clock" class="size-4" />
                Next target {{ $nearestTarget->target_at->format('j M, H:i') }} · {{ $nearestTarget->title }}
            </p>
        @endif
    </div>
</a>
