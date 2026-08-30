<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-8" data-dashboard-church="{{ $snapshot->churchName }}">
        <header class="overflow-hidden rounded-2xl border border-amber-200/70 bg-gradient-to-br from-amber-50 via-white to-white px-5 py-6 sm:px-7 sm:py-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-amber-700">Church workspace</p>
                    <h1 class="mt-2 break-words text-3xl font-bold tracking-tight text-gray-950 sm:text-4xl">{{ $snapshot->churchName }}</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 sm:text-base">See what needs attention, understand what is happening, and move Church work forward.</p>
                </div>

                @if ($snapshot->trial)
                    <div class="shrink-0 rounded-xl border border-amber-200 bg-white/90 px-4 py-3" aria-label="Trial status">
                        <p class="text-sm font-semibold text-gray-950">{{ $snapshot->trial['label'] }}</p>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $snapshot->trial['days_remaining'] === 1 ? '1 day remaining' : $snapshot->trial['days_remaining'].' days remaining' }}
                        </p>
                    </div>
                @endif
            </div>
        </header>

        <section aria-labelledby="action-center-heading">
            <div class="mb-4">
                <h2 id="action-center-heading" class="text-xl font-bold text-gray-950 sm:text-2xl">Needs your attention</h2>
                <p class="mt-1 text-sm leading-6 text-gray-600">Only work you can resolve in this Church appears here.</p>
            </div>

            @if (count($snapshot->actions))
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
                    @foreach ($snapshot->actions as $action)
                        <article class="grid gap-4 border-b border-gray-100 px-5 py-5 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-6">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-semibold text-amber-700">{{ $action->category }}</span>
                                    @if ($action->count !== null)
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700" aria-label="{{ $action->count }} items">{{ $action->count }}</span>
                                    @endif
                                </div>
                                <h3 class="mt-2 text-base font-bold text-gray-950">{{ $action->title }}</h3>
                                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600">{{ $action->description }}</p>
                            </div>
                            <x-filament::button
                                :href="$action->destination"
                                tag="a"
                                color="warning"
                                size="lg"
                                class="min-h-11 w-full sm:w-auto"
                                wire:navigate
                            >
                                {{ $action->label }}
                            </x-filament::button>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 px-5 py-6" role="status">
                    <div class="flex items-start gap-3">
                        <x-filament::icon icon="heroicon-o-check-circle" class="mt-0.5 h-6 w-6 shrink-0 text-emerald-700" />
                        <div>
                            <h3 class="font-bold text-emerald-950">You're all caught up.</h3>
                            <p class="mt-1 text-sm leading-6 text-emerald-800">There is no work currently waiting for your action.</p>
                        </div>
                    </div>
                </div>
            @endif
        </section>

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.8fr)]">
            <div class="space-y-8">
                @if (count($snapshot->guidance))
                    <section aria-labelledby="setup-guidance-heading">
                        <h2 id="setup-guidance-heading" class="text-xl font-bold text-gray-950">Set up when you are ready</h2>
                        <p class="mt-1 text-sm leading-6 text-gray-600">Optional recommendations based on your Church's current setup.</p>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($snapshot->guidance as $item)
                                <article class="rounded-2xl border border-gray-200 bg-white p-5">
                                    <h3 class="font-bold text-gray-950">{{ $item->title }}</h3>
                                    <p class="mt-2 text-sm leading-6 text-gray-600">{{ $item->description }}</p>
                                    <a href="{{ $item->destination }}" class="mt-4 inline-flex min-h-11 items-center text-sm font-bold text-amber-700 hover:text-amber-800 focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600" wire:navigate>{{ $item->label }}</a>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if (count($snapshot->summaries))
                    <section aria-labelledby="operational-summary-heading">
                        <h2 id="operational-summary-heading" class="text-xl font-bold text-gray-950">What's happening</h2>
                        <div class="mt-4 space-y-4">
                            @foreach ($snapshot->summaries as $key => $metrics)
                                <div class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6" data-summary="{{ $key }}">
                                    <h3 class="text-base font-bold capitalize text-gray-950">{{ $key }}</h3>
                                    <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                                        @foreach ($metrics as $metric)
                                            <div class="min-w-0">
                                                <dt class="text-xs font-semibold text-gray-500">{{ $metric->label }}</dt>
                                                <dd class="mt-1 text-2xl font-bold tracking-tight text-gray-950">{{ $metric->value }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            @if (count($snapshot->shortcuts))
                <aside aria-labelledby="workspace-shortcuts-heading">
                    <h2 id="workspace-shortcuts-heading" class="text-xl font-bold text-gray-950">Continue your work</h2>
                    <nav class="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-white" aria-label="Church workspace shortcuts">
                        @foreach ($snapshot->shortcuts as $shortcut)
                            <a href="{{ $shortcut['destination'] }}" class="group flex min-h-16 items-center justify-between gap-4 border-b border-gray-100 px-5 py-4 last:border-b-0 hover:bg-amber-50 focus-visible:relative focus-visible:z-10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-600" wire:navigate>
                                <span class="min-w-0">
                                    <span class="block font-bold text-gray-950">{{ $shortcut['label'] }}</span>
                                    <span class="mt-1 block text-sm leading-5 text-gray-600">{{ $shortcut['description'] }}</span>
                                </span>
                                <x-filament::icon icon="heroicon-o-arrow-right" class="h-5 w-5 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-amber-700" />
                            </a>
                        @endforeach
                    </nav>
                </aside>
            @endif
        </div>
    </div>
</x-filament-panels::page>
