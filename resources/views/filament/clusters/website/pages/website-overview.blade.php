<x-filament-panels::page>
    <div class="space-y-8">
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
