<x-filament-panels::page>
    <p class="text-sm text-gray-500">
        A calm overview of prayer requests and care activity at {{ \App\Support\CurrentChurch::name() }}.
    </p>

    @php
        $statusCounts = $this->getStatusCounts();
    @endphp

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
            <div class="text-sm font-medium text-gray-500">New</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $statusCounts['new'] ?? 0 }}</div>
        </div>
        <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
            <div class="text-sm font-medium text-gray-500">Reviewed</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $statusCounts['reviewed'] ?? 0 }}</div>
        </div>
        <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
            <div class="text-sm font-medium text-gray-500">Praying</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $statusCounts['praying'] ?? 0 }}</div>
        </div>
        <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
            <div class="text-sm font-medium text-gray-500">Closed</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $statusCounts['closed'] ?? 0 }}</div>
        </div>
        <div class="rounded-2xl bg-[#1E5631] p-6">
            <div class="text-sm font-medium text-white/80">Open Requests</div>
            <div class="mt-1 text-2xl font-bold text-white">{{ $this->getTotalOpenCount() }}</div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900">Needs Attention</h2>
            <p class="text-sm text-gray-500 mt-1">New prayer requests waiting for review.</p>

            <div class="mt-4 space-y-3">
                @forelse ($this->getNeedsAttention() as $prayerRequest)
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 pb-3 last:border-b-0 last:pb-0">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-900 truncate">
                                {{ $prayerRequest->title ?: \Illuminate\Support\Str::limit($prayerRequest->request, 40) }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $prayerRequest->requester_name ?: 'Anonymous' }}
                            </div>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium {{ $this->statusBadgeClasses($prayerRequest->status) }}">
                            {{ $prayerRequest->status->label() }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No new prayer requests are waiting for review.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900">Currently Praying</h2>
            <p class="text-sm text-gray-500 mt-1">Prayer requests currently being prayed over at {{ \App\Support\CurrentChurch::name() }}.</p>

            <div class="mt-4 space-y-3">
                @forelse ($this->getCurrentlyPraying() as $prayerRequest)
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 pb-3 last:border-b-0 last:pb-0">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-900 truncate">
                                {{ $prayerRequest->title ?: \Illuminate\Support\Str::limit($prayerRequest->request, 40) }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $prayerRequest->requester_name ?: 'Anonymous' }}
                            </div>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium {{ $this->statusBadgeClasses($prayerRequest->status) }}">
                            {{ $prayerRequest->status->label() }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No prayer requests are currently marked as praying.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="text-base font-bold text-gray-900">Recently Submitted</h2>
        <p class="text-sm text-gray-500 mt-1">Recent prayer requests for {{ \App\Support\CurrentChurch::name() }}.</p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                        <th class="pb-2 pr-4">Title</th>
                        <th class="pb-2 pr-4">Requester</th>
                        <th class="pb-2 pr-4">Status</th>
                        <th class="pb-2 pr-4">Visibility</th>
                        <th class="pb-2">Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getRecentlySubmitted() as $prayerRequest)
                        <tr class="border-t border-gray-100">
                            <td class="py-2 pr-4 font-medium text-gray-900">
                                {{ $prayerRequest->title ?: \Illuminate\Support\Str::limit($prayerRequest->request, 30) }}
                            </td>
                            <td class="py-2 pr-4 text-gray-500">
                                {{ $prayerRequest->requester_name ?: 'Anonymous' }}
                            </td>
                            <td class="py-2 pr-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $this->statusBadgeClasses($prayerRequest->status) }}">
                                    {{ $prayerRequest->status->label() }}
                                </span>
                            </td>
                            <td class="py-2 pr-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $this->visibilityBadgeClasses($prayerRequest->visibility) }}">
                                    {{ $this->visibilityLabel($prayerRequest->visibility) }}
                                </span>
                            </td>
                            <td class="py-2 text-gray-500">
                                {{ ($prayerRequest->submitted_at ?? $prayerRequest->created_at)?->format('M j, Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-sm text-gray-400">
                                No prayer requests yet. Prayer requests for {{ \App\Support\CurrentChurch::name() }} will appear here.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
