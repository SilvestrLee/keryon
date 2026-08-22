<x-filament-panels::page>
    <div class="space-y-8">
        <section class="rounded-2xl border border-gray-200 bg-white p-5" aria-labelledby="website-publication-status">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Public Website</p>
                    <h2 id="website-publication-status" class="mt-1 text-lg font-semibold text-gray-900">
                        @if ($publicationStatus['state'] === 'published')
                            Published
                        @elseif ($publicationStatus['state'] === 'offline')
                            Offline
                        @else
                            Not published yet
                        @endif
                    </h2>
                    <p class="mt-1 max-w-2xl text-sm text-gray-600">
                        @if ($publicationStatus['state'] === 'published' && $publicationStatus['pending'])
                            Your public Website is live. You have unpublished changes ready to preview and publish.
                        @elseif ($publicationStatus['state'] === 'published')
                            Your public Website matches your current working content.
                        @elseif ($publicationStatus['state'] === 'offline')
                            Your Website is private. Your working content and previous publication are preserved.
                        @else
                            Your church Website is still private. Preview it before the first publication.
                        @endif
                    </p>
                </div>
                @if ($publicationStatus['state'] === 'published')
                    <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold {{ $publicationStatus['pending'] ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-700' }}">
                        {{ $publicationStatus['pending'] ? 'Unpublished changes' : 'Up to date' }}
                    </span>
                @endif
            </div>

            @if ($publicationStatus['latest'])
                <p class="mt-4 text-xs text-gray-500">
                    Last published {{ $publicationStatus['latest']->published_at->format('j M Y, H:i') }}
                    @if ($publicationStatus['latest']->publisher)
                        by {{ $publicationStatus['latest']->publisher->name }}
                    @endif
                </p>
            @endif
        </section>

        <div>
            <h2 class="text-base font-semibold" style="color: #132E35">Pages</h2>
            <p class="mt-1 text-sm text-gray-500">What your church website says, organized the way visitors see it.</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($pages as $page)
                    <a
                        href="{{ $page['url'] }}"
                        class="group flex flex-col rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-[#E09F3E] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E09F3E]"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <x-filament::icon :icon="$page['icon']" class="h-6 w-6" style="color: #132E35" />
                            @if (($page['started'] ?? false))
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Started</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Not started</span>
                            @endif
                        </div>
                        <p class="mt-3 text-sm font-semibold text-gray-900">
                            {{ $page['label'] }}
                            @if (isset($page['count']))
                                <span class="font-normal text-gray-400">({{ $page['count'] }})</span>
                            @endif
                        </p>
                        <p class="mt-1 text-xs leading-relaxed text-gray-500">{{ $page['description'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <a
                href="{{ \App\Filament\Clusters\Website\Pages\EditChurchInformation::getUrl() }}"
                class="rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-[#E09F3E] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E09F3E]"
            >
                <x-filament::icon icon="heroicon-o-building-library" class="h-6 w-6" style="color: #132E35" />
                <p class="mt-3 text-sm font-semibold text-gray-900">Church Information</p>
                <p class="mt-1 text-xs leading-relaxed text-gray-500">
                    Shared across Keryon — name, contact, service times, social links.
                </p>
                <p class="mt-3 text-xs font-medium {{ $churchInformationConfigured ? 'text-emerald-700' : 'text-gray-400' }}">
                    {{ $churchInformationConfigured ? 'Available' : 'Not yet added' }}
                </p>
            </a>

            <a
                href="{{ \App\Filament\Clusters\Website\Pages\EditBrand::getUrl() }}"
                class="rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-[#E09F3E] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E09F3E]"
            >
                <x-filament::icon icon="heroicon-o-swatch" class="h-6 w-6" style="color: #132E35" />
                <p class="mt-3 text-sm font-semibold text-gray-900">Brand</p>
                <p class="mt-1 text-xs leading-relaxed text-gray-500">
                    Your church's logo, colors, and typography.
                </p>
                <p class="mt-3 text-xs font-medium {{ $brandConfigured ? 'text-emerald-700' : 'text-gray-400' }}">
                    {{ $brandConfigured ? 'Configured' : 'Not configured yet' }}
                </p>
            </a>

            <a
                href="{{ \App\Filament\Clusters\Website\Pages\EditTheme::getUrl() }}"
                class="rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-[#E09F3E] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E09F3E]"
            >
                <x-filament::icon icon="heroicon-o-paint-brush" class="h-6 w-6" style="color: #132E35" />
                <p class="mt-3 text-sm font-semibold text-gray-900">Theme</p>
                <p class="mt-1 text-xs leading-relaxed text-gray-500">
                    The professional design your website uses.
                </p>
                <p class="mt-3 text-xs font-medium text-gray-700">
                    {{ $theme?->label() ?? \App\Enums\WebsiteTheme::PROCLAIM->label() }}
                </p>
            </a>
        </div>

        @unless ($canManageContent || $canManageBrand || $canManageTheme)
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-600">
                You can view your church's website information here, but your current role doesn't include making changes to it.
            </div>
        @endunless
    </div>
</x-filament-panels::page>
