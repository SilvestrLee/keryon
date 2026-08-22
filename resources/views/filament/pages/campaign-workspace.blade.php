<x-filament-panels::page>
    @php
        $campaign = $this->campaign();
        $counts = $this->readinessCounts();
        $activeCommunications = $campaign->communications->whereNull('cancelled_at')->values();
        $cancelledCommunications = $campaign->communications->whereNotNull('cancelled_at')->values();
        $upcoming = $this->upcomingTargets();
    @endphp

    <div class="space-y-8">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white" aria-labelledby="campaign-overview-heading">
            <div class="border-l-4 border-[#1E5631] p-5 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-filament::badge :color="match ($campaign->status) {
                                \App\Enums\CampaignStatus::ACTIVE => 'success',
                                \App\Enums\CampaignStatus::PLANNED => 'info',
                                \App\Enums\CampaignStatus::COMPLETED => 'primary',
                                default => 'gray',
                            }">{{ $campaign->status->label() }}</x-filament::badge>
                            <span class="text-sm text-gray-500">
                                @if ($campaign->starts_on && $campaign->ends_on)
                                    {{ $campaign->starts_on->format('j M Y') }} – {{ $campaign->ends_on->format('j M Y') }}
                                @elseif ($campaign->starts_on)
                                    Starts {{ $campaign->starts_on->format('j M Y') }}
                                @elseif ($campaign->ends_on)
                                    Ends {{ $campaign->ends_on->format('j M Y') }}
                                @else
                                    Dates not set
                                @endif
                            </span>
                        </div>
                        <h2 id="campaign-overview-heading" class="sr-only">Campaign overview</h2>
                        @if (! $campaign->purpose)
                            <p class="mt-3 text-sm text-gray-500">Add a purpose so your team understands what this Campaign needs to communicate.</p>
                        @endif
                    </div>
                    <button
                        type="button"
                        wire:click="mountAction('addCommunication')"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#1E5631] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#174527] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1E5631]"
                    >
                        <x-filament::icon icon="heroicon-o-plus" class="size-5" />
                        Add communication
                    </button>
                </div>
            </div>
        </section>

        <section aria-labelledby="readiness-heading">
            <div class="mb-3">
                <h2 id="readiness-heading" class="text-base font-semibold text-gray-950">Communication readiness</h2>
                <p class="mt-1 text-sm text-gray-500">Real preparation state from linked Content Studio content. Cancelled communications are excluded.</p>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <div class="rounded-2xl border border-gray-200 bg-white p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Ready</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ $counts['prepared'] }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Awaiting approval</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-700">{{ $counts['awaiting_approval'] }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">In preparation</p>
                    <p class="mt-1 text-2xl font-semibold text-blue-700">{{ $counts['in_preparation'] }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Not started</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-800">{{ $counts['not_started'] }}</p>
                </div>
                <div class="col-span-2 rounded-2xl border border-gray-200 bg-gray-50 p-4 sm:col-span-1">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Planned</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950">{{ $counts['total'] }}</p>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="rounded-2xl border border-gray-200 bg-white" aria-labelledby="communication-plan-heading">
                <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4">
                    <div>
                        <h2 id="communication-plan-heading" class="font-semibold text-gray-950">Communication Plan</h2>
                        <p class="mt-1 text-sm text-gray-500">What your church needs to prepare for this initiative.</p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">{{ $activeCommunications->count() }}</span>
                </div>

                <div class="divide-y divide-gray-100">
                    @forelse ($activeCommunications as $index => $communication)
                        <article wire:key="campaign-communication-{{ $communication->id }}" class="p-4 sm:p-5">
                            <div class="flex items-start gap-3">
                                <div class="flex shrink-0 flex-col gap-1" aria-label="Reorder {{ $communication->title }}">
                                    <button
                                        type="button"
                                        wire:click="moveCommunication({{ $communication->id }}, 'up')"
                                        @disabled($loop->first)
                                        aria-label="Move {{ $communication->title }} earlier"
                                        class="flex size-10 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600 disabled:cursor-not-allowed disabled:opacity-30"
                                    ><x-filament::icon icon="heroicon-o-chevron-up" class="size-4" /></button>
                                    <button
                                        type="button"
                                        wire:click="moveCommunication({{ $communication->id }}, 'down')"
                                        @disabled($loop->last)
                                        aria-label="Move {{ $communication->title }} later"
                                        class="flex size-10 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600 disabled:cursor-not-allowed disabled:opacity-30"
                                    ><x-filament::icon icon="heroicon-o-chevron-down" class="size-4" /></button>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <h3 class="font-medium text-gray-950">{{ $communication->title }}</h3>
                                            @if ($communication->purpose)
                                                <p class="mt-1 text-sm leading-6 text-gray-600">{{ $communication->purpose }}</p>
                                            @endif
                                        </div>
                                        <x-filament::badge :color="$this->readinessColor($communication)">{{ $this->readinessLabel($communication) }}</x-filament::badge>
                                    </div>

                                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700">
                                            <x-filament::icon icon="heroicon-o-paper-airplane" class="size-3.5" />
                                            {{ $communication->channel->label() }}
                                        </span>
                                        @if ($communication->target_at)
                                            <span class="inline-flex items-center gap-1.5">
                                                <x-filament::icon icon="heroicon-o-clock" class="size-4" />
                                                Target {{ $communication->target_at->format('j M Y, H:i') }}
                                            </span>
                                        @endif
                                    </div>

                                    @if ($communication->content_item_id)
                                        <div class="mt-3 rounded-xl bg-gray-50 px-3 py-2.5 text-sm">
                                            @if ($url = $this->contentItemUrl($communication))
                                                <a href="{{ $url }}" wire:navigate class="font-medium text-[#1E5631] underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                                                    {{ $communication->contentItem->title }}
                                                </a>
                                                <span class="ml-2 text-gray-500">Content Studio · {{ $communication->contentItem->status->label() }}</span>
                                            @else
                                                <span class="font-medium text-red-700">Linked content is unavailable</span>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <button type="button" wire:click="mountAction('editCommunication', { communication: {{ $communication->id }} })" class="min-h-10 rounded-lg px-3 text-sm font-medium text-gray-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600">Edit</button>
                                        @if ($communication->content_item_id)
                                            @if ($url = $this->contentItemUrl($communication))
                                                <a href="{{ $url }}" wire:navigate class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-medium text-[#1E5631] hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600">View content</a>
                                            @endif
                                            <button type="button" wire:click="mountAction('unlinkCommunicationContent', { communication: {{ $communication->id }} })" class="min-h-10 rounded-lg px-3 text-sm font-medium text-gray-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600">Unlink content</button>
                                        @else
                                            @if ($this->canCreateContextualContent($communication))
                                                <a href="{{ $this->createContentUrl($communication) }}" wire:navigate class="inline-flex min-h-10 items-center rounded-lg bg-[#1E5631] px-3 text-sm font-semibold text-white hover:bg-[#174527] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1E5631]">Create content</a>
                                            @endif
                                            @if ($this->canCreateContextualFaithFlow($communication))
                                                <a href="{{ $this->createWithFaithFlowUrl($communication) }}" wire:navigate class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-medium text-[#1E5631] hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600">Create with FaithFlow</a>
                                            @endif
                                            @if ($this->canLinkExistingContent())
                                                <button type="button" wire:click="mountAction('linkExistingContent', { communication: {{ $communication->id }} })" class="min-h-10 rounded-lg px-3 text-sm font-medium text-gray-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600">Link existing content</button>
                                            @endif
                                        @endif
                                        <button type="button" wire:click="mountAction('cancelCommunication', { communication: {{ $communication->id }} })" class="min-h-10 rounded-lg px-3 text-sm font-medium text-red-700 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-600">Cancel communication</button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="px-6 py-12 text-center">
                            <div class="mx-auto flex size-11 items-center justify-center rounded-xl bg-emerald-50 text-[#1E5631]">
                                <x-filament::icon icon="heroicon-o-list-bullet" class="size-5" />
                            </div>
                            <h3 class="mt-4 font-semibold text-gray-950">Build the communication plan</h3>
                            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-600">Add the communications your church needs to prepare for this initiative.</p>
                            <button type="button" wire:click="mountAction('addCommunication')" class="mt-5 min-h-11 rounded-xl bg-[#1E5631] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#174527] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1E5631]">Add first communication</button>
                        </div>
                    @endforelse
                </div>
            </section>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-5" aria-labelledby="upcoming-targets-heading">
                    <h2 id="upcoming-targets-heading" class="font-semibold text-gray-950">Upcoming targets</h2>
                    <p class="mt-1 text-sm text-gray-500">Planning dates only—not scheduled publishing.</p>
                    <div class="mt-4 space-y-4">
                        @forelse ($upcoming as $communication)
                            <div class="border-l-2 border-amber-400 pl-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $communication->target_at->format('j M · H:i') }}</p>
                                <p class="mt-1 text-sm font-medium text-gray-900">{{ $communication->title }}</p>
                                <p class="text-xs text-gray-500">{{ $communication->channel->label() }}</p>
                            </div>
                        @empty
                            <p class="text-sm leading-6 text-gray-500">No upcoming target dates. Add one when a communication needs a clear planning deadline.</p>
                        @endforelse
                    </div>
                </section>

                @if ($cancelledCommunications->isNotEmpty())
                    <details class="group rounded-2xl border border-gray-200 bg-white">
                        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 p-5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                            <span class="font-semibold text-gray-900">Cancelled <span class="text-sm font-normal text-gray-500">{{ $cancelledCommunications->count() }}</span></span>
                            <x-filament::icon icon="heroicon-o-chevron-down" class="size-5 text-gray-400 transition group-open:rotate-180" />
                        </summary>
                        <div class="space-y-3 border-t border-gray-100 p-4">
                            @foreach ($cancelledCommunications as $communication)
                                <div class="rounded-xl bg-gray-50 p-3">
                                    <p class="text-sm font-medium text-gray-700 line-through">{{ $communication->title }}</p>
                                    <button type="button" wire:click="mountAction('restoreCommunication', { communication: {{ $communication->id }} })" class="mt-2 min-h-10 text-sm font-medium text-[#1E5631] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600">Restore to plan</button>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
            </aside>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            @if ($this->canViewCampaignMedia())
                <section class="rounded-2xl border border-gray-200 bg-white" aria-labelledby="campaign-media-heading">
                    <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 id="campaign-media-heading" class="font-semibold text-gray-950">Campaign Media</h2>
                            <p class="mt-1 text-sm text-gray-500">Institutional assets relevant to this initiative.</p>
                        </div>
                        @if ($this->canManageCampaignMedia())
                            <button type="button" wire:click="mountAction('attachMedia')" class="inline-flex min-h-10 items-center justify-center rounded-lg px-3 text-sm font-semibold text-[#1E5631] hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600">Attach existing media</button>
                        @endif
                    </div>
                    <div class="grid gap-3 p-4 sm:grid-cols-2">
                        @forelse ($campaign->mediaAssociations as $association)
                            @php
                                $preview = $this->campaignMediaPreview($association);
                            @endphp
                            <article wire:key="campaign-media-{{ $association->id }}" class="overflow-hidden rounded-xl border border-gray-200">
                                @if ($preview)
                                    <img src="{{ $preview['url'] }}" alt="{{ $preview['alt'] }}" width="{{ $preview['width'] ?? '' }}" height="{{ $preview['height'] ?? '' }}" class="aspect-[16/9] w-full bg-gray-100 object-cover" loading="lazy">
                                @else
                                    <div class="flex aspect-[16/9] items-center justify-center bg-gray-100 px-4 text-center text-sm text-gray-500">
                                        Asset preview unavailable
                                    </div>
                                @endif
                                <div class="p-3">
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $association->label ?: ($association->mediaAsset?->original_filename ?? 'Unavailable asset') }}</h3>
                                    @if ($association->label && $association->mediaAsset)
                                        <p class="mt-1 truncate text-xs text-gray-500">{{ $association->mediaAsset->original_filename }}</p>
                                    @endif
                                    @if ($this->canManageCampaignMedia())
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($association->mediaAsset && ! $association->mediaAsset->trashed())
                                                <button type="button" wire:click="mountAction('editMediaAssociation', { association: {{ $association->id }} })" class="min-h-10 rounded-lg px-3 text-sm font-medium text-gray-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600">Edit label</button>
                                            @endif
                                            <button type="button" wire:click="mountAction('detachMedia', { association: {{ $association->id }} })" class="min-h-10 rounded-lg px-3 text-sm font-medium text-red-700 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-600">Remove</button>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="sm:col-span-2 rounded-xl border border-dashed border-gray-300 px-5 py-8 text-center">
                                <p class="text-sm font-medium text-gray-900">No Campaign media attached</p>
                                <p class="mt-1 text-sm text-gray-500">Attach existing institutional media to keep the initiative's visual assets together.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            @endif

            @php
                $websiteCommunications = $this->websiteCommunications();
                $websiteStatus = $this->websiteOperationalStatus();
            @endphp
            @if ($websiteCommunications->isNotEmpty() && $websiteStatus)
                <section class="rounded-2xl border border-gray-200 bg-white p-5" aria-labelledby="website-coordination-heading">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Website coordination</p>
                            <h2 id="website-coordination-heading" class="mt-1 font-semibold text-gray-950">Website action required</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-600">{{ $websiteCommunications->count() }} planned Website {{ \Illuminate\Support\Str::plural('communication', $websiteCommunications->count()) }}. Content readiness does not mean the Website has been updated.</p>
                        </div>
                        <a href="{{ $this->websiteOverviewUrl() }}" wire:navigate class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-[#1E5631] px-4 text-sm font-semibold text-white hover:bg-[#174527] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1E5631]">Open Website</a>
                    </div>
                    <div class="mt-5 rounded-xl bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Church Website operational state</p>
                        <p class="mt-1 text-sm font-medium text-gray-900">
                            @if ($websiteStatus['state'] === 'published')
                                {{ $websiteStatus['pending'] ? 'Published · unpublished Website changes exist' : 'Published · working Website is up to date' }}
                            @elseif ($websiteStatus['state'] === 'offline')
                                Website offline
                            @else
                                Website not published yet
                            @endif
                        </p>
                        <p class="mt-2 text-xs leading-5 text-gray-500">This is the Church Website's overall state—not proof that this Campaign's Website communication is complete or live.</p>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
