<x-filament-panels::page>
    <div class="space-y-8">
        <div class="max-w-3xl">
            <p class="text-sm leading-6 text-gray-600">
                Plan and track the communications your church needs for important initiatives.
            </p>
        </div>

        @php
            $sections = $this->campaignSections();
            $hasCampaigns = collect($sections)->contains(fn ($campaigns) => $campaigns->isNotEmpty());
        @endphp

        @if (! $hasCampaigns)
            <section class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center" aria-labelledby="campaigns-empty-heading">
                <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-emerald-50 text-[#1E5631]">
                    <x-filament::icon icon="heroicon-o-megaphone" class="size-6" />
                </div>
                <h2 id="campaigns-empty-heading" class="mt-4 text-lg font-semibold text-gray-950">Plan your first Campaign</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-gray-600">
                    Campaigns help your Communications team coordinate the messages needed for Easter, Christmas, outreach, conferences and other church initiatives.
                </p>
                <div class="mt-6">
                    {{ $this->createCampaignAction }}
                </div>
            </section>
        @else
            @foreach ($sections as $heading => $campaigns)
                @continue($campaigns->isEmpty())

                @if ($heading === 'Archived')
                    <details class="group rounded-2xl border border-gray-200 bg-white">
                        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                            <span>
                                <span class="font-semibold text-gray-900">Archived</span>
                                <span class="ml-2 text-sm text-gray-500">{{ $campaigns->count() }}</span>
                            </span>
                            <x-filament::icon icon="heroicon-o-chevron-down" class="size-5 text-gray-400 transition group-open:rotate-180" />
                        </summary>
                        <div class="grid gap-4 border-t border-gray-100 p-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($campaigns as $campaign)
                                @include('filament.pages.partials.campaign-card', ['campaign' => $campaign])
                            @endforeach
                        </div>
                    </details>
                @else
                    <section aria-labelledby="campaign-section-{{ \Illuminate\Support\Str::slug($heading) }}">
                        <div class="mb-3 flex items-center gap-2">
                            <h2 id="campaign-section-{{ \Illuminate\Support\Str::slug($heading) }}" class="text-base font-semibold text-gray-950">{{ $heading }}</h2>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $campaigns->count() }}</span>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($campaigns as $campaign)
                                @include('filament.pages.partials.campaign-card', ['campaign' => $campaign])
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
