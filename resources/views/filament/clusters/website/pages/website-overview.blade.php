<x-filament-panels::page>
    <div class="website-overview space-y-10">
        @if ($isEmptyWebsite)
            <section class="rounded-2xl border border-amber-200 bg-amber-50/60 px-5 py-4" aria-labelledby="website-empty-heading">
                <h2 id="website-empty-heading" class="text-base font-semibold text-[#132E35]">Your Website is ready to set up.</h2>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-600">Start with the pages visitors will see, then add your Church identity, brand, theme, and domain when you're ready.</p>
            </section>
        @endif

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white" aria-labelledby="website-status-heading">
            <div class="border-b border-gray-100 px-5 py-5 sm:px-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-500">Website status</p>
                        <h2 id="website-status-heading" class="mt-1 text-xl font-semibold tracking-tight text-[#132E35]">
                            @if ($publicationStatus['state'] === 'published')
                                Live
                            @elseif ($publicationStatus['state'] === 'offline')
                                Offline
                            @else
                                Not published yet
                            @endif
                        </h2>
                        <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-600">
                            @if ($publicationStatus['state'] === 'published' && $publicationStatus['pending'])
                                Your Website is live, with changes ready to preview and publish.
                            @elseif ($publicationStatus['state'] === 'published')
                                Visitors can see your current published Website.
                            @elseif ($publicationStatus['state'] === 'offline')
                                Your Website is private. Your content and publication history are preserved.
                            @else
                                Your Website is private until you publish it. Preview your work when you're ready.
                            @endif
                        </p>
                    </div>
                    @if ($publicationStatus['state'] === 'published')
                        <span class="inline-flex w-fit rounded-lg px-2.5 py-1 text-xs font-semibold {{ $publicationStatus['pending'] ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-700' }}">
                            {{ $publicationStatus['pending'] ? 'Changes not published' : 'Up to date' }}
                        </span>
                    @endif
                </div>
            </div>

            <dl class="grid divide-y divide-gray-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                <div class="min-w-0 px-5 py-5 sm:px-6">
                    <dt class="text-xs font-semibold text-gray-500">Official address</dt>
                    <dd class="mt-2 break-words text-sm font-semibold text-gray-900">
                        {{ $domainSummary?->officialUrl ?? $domainSummary?->keryonUrl ?? 'Not available' }}
                    </dd>
                    @if ($publicationStatus['latest'])
                        <p class="mt-2 text-xs leading-5 text-gray-500">Last published {{ $publicationStatus['latest']->published_at->format('j M Y, H:i') }}</p>
                    @elseif ($publicationStatus['state'] !== 'published')
                        <p class="mt-2 text-xs leading-5 text-gray-500">This address becomes public when you publish.</p>
                    @endif
                </div>

                <div class="min-w-0 px-5 py-5 sm:px-6">
                    <dt class="text-xs font-semibold text-gray-500">Domains</dt>
                    <dd class="mt-2 break-words text-sm font-semibold text-gray-900">{{ $domainSummary?->headline }}</dd>
                    <p class="mt-1 text-xs leading-5 {{ $domainSummary?->state === 'degraded' ? 'text-amber-800' : 'text-gray-500' }}">{{ $domainSummary?->detail }}</p>
                    @if ($domainSummary?->hasHealthyCustomPrimary || $domainSummary?->state === 'degraded')
                        <p class="mt-2 break-words text-xs text-gray-500">Keryon fallback: {{ $domainSummary->keryonUrl }}</p>
                    @endif
                    @if ($canManageDomains)
                        <a href="{{ $domainsUrl }}" wire:navigate class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-amber-800 hover:text-amber-950 focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-700">
                            {{ $domainSummary?->state === 'pending' ? 'Continue setup' : ($domainSummary?->state === 'degraded' ? 'Review domains' : 'Manage domains') }}
                            <span aria-hidden="true">→</span>
                        </a>
                    @else
                        <p class="mt-3 text-xs text-gray-400">Only your Primary Administrator can manage website domains.</p>
                    @endif
                </div>
            </dl>
        </section>

        <section aria-labelledby="website-pages-heading">
            <div>
                <h2 id="website-pages-heading" class="text-lg font-semibold tracking-tight text-[#132E35]">Pages</h2>
                <p class="mt-1 text-sm text-gray-500">Build the pages visitors see.</p>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($pages as $page)
                    <a href="{{ $page['url'] }}" class="group flex min-h-40 flex-col rounded-2xl border border-gray-200 bg-white p-5 transition duration-200 hover:-translate-y-0.5 hover:border-amber-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-700">
                        <div class="flex items-start justify-between gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-50 text-[#132E35]">
                                <x-filament::icon :icon="$page['icon']" class="h-5 w-5" />
                            </span>
                            <span class="text-xs font-medium {{ ($page['started'] ?? false) ? 'text-emerald-700' : 'text-gray-500' }}">
                                {{ ($page['started'] ?? false) ? 'In progress' : 'Not started' }}
                            </span>
                        </div>
                        <h3 class="mt-4 text-sm font-semibold text-gray-950">
                            {{ $page['label'] }}
                            @if (isset($page['count']))<span class="font-medium text-gray-400">({{ $page['count'] }})</span>@endif
                        </h3>
                        <p class="mt-1 text-xs leading-5 text-gray-500">{{ $page['description'] }}</p>
                        <span class="mt-auto pt-4 text-xs font-semibold text-amber-800">{{ ($page['started'] ?? false) ? 'Manage page' : 'Start page' }} <span aria-hidden="true">→</span></span>
                    </a>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="website-setup-heading">
            <div>
                <h2 id="website-setup-heading" class="text-lg font-semibold tracking-tight text-[#132E35]">Site setup</h2>
                <p class="mt-1 text-sm text-gray-500">Identity and configuration shared across your public Website.</p>
            </div>

            <div class="mt-4 grid overflow-hidden rounded-2xl border border-gray-200 bg-white sm:grid-cols-2 xl:grid-cols-4">
                <a href="{{ \App\Filament\Clusters\Website\Pages\EditChurchInformation::getUrl() }}" class="group flex min-h-36 flex-col border-b border-gray-100 p-5 transition hover:bg-amber-50/40 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-700 sm:border-r xl:border-b-0">
                    <x-filament::icon icon="heroicon-o-building-library" class="h-5 w-5 text-[#132E35]" />
                    <h3 class="mt-3 text-sm font-semibold text-gray-950">Church Information</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ $churchInformationConfigured ? 'Available' : 'Not yet added' }}</p>
                    <span class="mt-auto pt-3 text-xs font-semibold text-amber-800">Manage <span aria-hidden="true">→</span></span>
                </a>

                <a href="{{ \App\Filament\Clusters\Website\Pages\EditBrand::getUrl() }}" class="group flex min-h-36 flex-col border-b border-gray-100 p-5 transition hover:bg-amber-50/40 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-700 xl:border-b-0 xl:border-r">
                    <x-filament::icon icon="heroicon-o-swatch" class="h-5 w-5 text-[#132E35]" />
                    <h3 class="mt-3 text-sm font-semibold text-gray-950">Brand</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ $brandConfigured ? 'Configured' : 'Not configured' }}</p>
                    <span class="mt-auto pt-3 text-xs font-semibold text-amber-800">{{ $brandConfigured ? 'Manage' : 'Set up' }} <span aria-hidden="true">→</span></span>
                </a>

                <a href="{{ \App\Filament\Clusters\Website\Pages\EditTheme::getUrl() }}" class="group flex min-h-36 flex-col border-b border-gray-100 p-5 transition hover:bg-amber-50/40 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-700 sm:border-r sm:border-b-0">
                    <x-filament::icon icon="heroicon-o-paint-brush" class="h-5 w-5 text-[#132E35]" />
                    <h3 class="mt-3 text-sm font-semibold text-gray-950">Theme</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ $theme?->label() ?? \App\Enums\WebsiteTheme::PROCLAIM->label() }}</p>
                    <span class="mt-auto pt-3 text-xs font-semibold text-amber-800">Manage <span aria-hidden="true">→</span></span>
                </a>

                @if ($canManageDomains)
                    <a href="{{ $domainsUrl }}" wire:navigate class="group flex min-h-36 flex-col p-5 transition hover:bg-amber-50/40 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-700">
                        <x-filament::icon icon="heroicon-o-link" class="h-5 w-5 text-[#132E35]" />
                        <h3 class="mt-3 text-sm font-semibold text-gray-950">Domains</h3>
                        <p class="mt-1 break-words text-xs text-gray-500">{{ $domainSummary?->headline }}</p>
                        <span class="mt-auto pt-3 text-xs font-semibold text-amber-800">Manage <span aria-hidden="true">→</span></span>
                    </a>
                @else
                    <article class="flex min-h-36 flex-col p-5" aria-label="Domain status">
                        <x-filament::icon icon="heroicon-o-link" class="h-5 w-5 text-[#132E35]" />
                        <h3 class="mt-3 text-sm font-semibold text-gray-950">Domains</h3>
                        <p class="mt-1 break-words text-xs text-gray-500">{{ $domainSummary?->headline }}</p>
                        <p class="mt-auto pt-3 text-xs text-gray-400">Only your Primary Administrator can manage website domains.</p>
                    </article>
                @endif
            </div>
        </section>

        @if ($recentProvenance->isNotEmpty())
            <section class="rounded-2xl bg-gray-50 p-5" aria-labelledby="website-sources-heading">
                <h2 id="website-sources-heading" class="text-base font-semibold text-[#132E35]">Recent communication sources</h2>
                <p class="mt-1 text-sm text-gray-600">Working Website content sources. Publication remains a separate action.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($recentProvenance as $source)
                        <article class="rounded-xl bg-white p-4">
                            <p class="text-sm font-semibold text-gray-900">{{ $source->contentItem?->title ?? 'Removed Content source' }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $source->destination->label() }}@if($source->campaign) · {{ $source->campaign->title }}@endif @if($source->campaignCommunication) · {{ $source->campaignCommunication->title }}@endif</p>
                            <p class="mt-2 text-xs text-gray-500">Applied by {{ $source->actor?->name ?? 'a former staff member' }} on {{ $source->applied_at->format('j M Y, H:i') }}</p>
                            @if ($publishedAttribution = $source->publicationAttributions->sortByDesc(fn ($item) => $item->publication?->published_at)->first())
                                <p class="mt-2 text-xs font-semibold text-emerald-700">Included in publication #{{ $publishedAttribution->website_publication_id }} on {{ $publishedAttribution->publication->published_at->format('j M Y, H:i') }}</p>
                            @else
                                <p class="mt-2 text-xs font-semibold text-amber-800">Not yet included in a Website publication.</p>
                            @endif
                            @if ($source->contentItem?->status === \App\Enums\ContentStatus::APPROVED && $source->contentItem->approved_at?->gt($source->source_approved_at))
                                <p class="mt-2 text-xs font-semibold text-amber-800">A newer approved source version is available.</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @unless ($canManageContent || $canManageBrand || $canManageTheme)
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-600">You can view your Church's Website information here, but your role does not include making changes.</div>
        @endunless
    </div>
</x-filament-panels::page>
