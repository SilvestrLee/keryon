<x-filament-panels::page>
    <div class="mx-auto w-full max-w-6xl space-y-6 pb-10">
        @if ($state && $state->status === $statuses::DISMISSED)
            <section class="overflow-hidden rounded-2xl border border-amber-200 bg-amber-50 p-6 sm:p-8">
                <div class="max-w-2xl">
                    <x-filament::icon icon="heroicon-o-arrow-path-rounded-square" class="h-8 w-8 text-amber-700" />
                    <h2 class="mt-5 text-2xl font-semibold tracking-tight text-gray-950">Church setup is ready when you are</h2>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-gray-700">Your workspace remained fully available. Resume from {{ strtolower($state->current_step->label()) }} whenever it suits your team.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <x-filament::button wire:click="resume">Resume setup</x-filament::button>
                        <x-filament::button color="gray" tag="a" :href="filament()->getHomeUrl()">Return to workspace</x-filament::button>
                    </div>
                </div>
            </section>
        @elseif (! $state)
            <section class="grid overflow-hidden rounded-2xl border border-gray-200 bg-white lg:grid-cols-[1.35fr_0.65fr]">
                <div class="p-7 sm:p-10 lg:p-12">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100 text-amber-800">
                        <x-filament::icon icon="heroicon-o-sparkles" class="h-6 w-6" />
                    </div>
                    <h2 class="mt-7 max-w-xl text-3xl font-semibold tracking-tight text-gray-950 sm:text-4xl">Welcome to Keryon</h2>
                    <p class="mt-3 max-w-xl text-base leading-7 text-gray-600">Your church workspace is ready. Confirm the essentials now, or enter Keryon and return whenever you like.</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <x-filament::button wire:click="start" size="lg">Set up church</x-filament::button>
                        <x-filament::button color="gray" size="lg" tag="a" :href="filament()->getHomeUrl()">Enter Keryon</x-filament::button>
                    </div>
                </div>
                <aside class="border-t border-gray-200 bg-gray-950 p-7 text-gray-100 sm:p-10 lg:border-l lg:border-t-0">
                    <p class="text-sm font-medium text-amber-300">Your trial</p>
                    @if ($trialEndsAt)
                        <p class="mt-4 text-4xl font-semibold tracking-tight">{{ $trialDaysRemaining }} {{ Str::plural('day', $trialDaysRemaining) }}</p>
                        <p class="mt-2 text-sm leading-6 text-gray-300">remaining in your 21-day trial. Trial timing comes directly from your Subscription.</p>
                        <p class="mt-8 text-sm text-gray-400">Ends {{ $trialEndsAt->timezone(auth()->user()->timezone ?? config('app.timezone'))->format('j M Y') }}</p>
                    @else
                        <p class="mt-4 text-xl font-semibold">Subscription status available in Keryon</p>
                    @endif
                </aside>
            </section>
        @else
            <header class="rounded-2xl border border-gray-200 bg-white px-5 py-5 sm:px-7">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-medium text-amber-700">Guided Church setup</p>
                        <h2 class="mt-1 text-2xl font-semibold tracking-tight text-gray-950">{{ $step->label() }}</h2>
                    </div>
                    <nav aria-label="Setup progress" class="overflow-x-auto pb-1">
                        <ol class="flex min-w-max items-center gap-2">
                            @foreach ($steps as $item)
                                @php
                                    $position = array_search($item, $steps, true);
                                    $currentPosition = array_search($step, $steps, true);
                                    $isPast = $position < $currentPosition;
                                    $isCurrent = $item === $step;
                                @endphp
                                <li class="flex items-center gap-2">
                                    <span @class([
                                        'flex h-9 items-center rounded-lg px-3 text-xs font-semibold',
                                        'bg-amber-100 text-amber-900' => $isCurrent,
                                        'text-gray-700' => $isPast,
                                        'text-gray-400' => ! $isPast && ! $isCurrent,
                                    ]) aria-current="{{ $isCurrent ? 'step' : 'false' }}">
                                        @if ($isPast)
                                            <x-filament::icon icon="heroicon-m-check" class="mr-1.5 h-4 w-4 text-amber-700" />
                                        @endif
                                        {{ $item->label() }}
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                </div>
            </header>

            @if ($step === App\Enums\ChurchOnboardingStep::IDENTITY)
                <section class="grid gap-6 lg:grid-cols-[1fr_18rem]">
                    <form wire:submit="saveIdentity" class="rounded-2xl border border-gray-200 bg-white p-6 sm:p-8">
                        <h3 class="text-xl font-semibold text-gray-950">Confirm your church essentials</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">These details are shared across Keryon. Public contact fields remain optional.</p>
                        <div class="mt-7 grid gap-5 sm:grid-cols-2">
                            <label class="grid gap-2 text-sm font-medium text-gray-800 sm:col-span-2">Church name
                                <input wire:model="name" class="rounded-lg border-gray-300 text-gray-950 focus:border-amber-600 focus:ring-amber-600" required />
                                @error('name') <span class="text-sm text-red-700">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-2 text-sm font-medium text-gray-800">Public email
                                <input type="email" wire:model="email" class="rounded-lg border-gray-300 text-gray-950 focus:border-amber-600 focus:ring-amber-600" />
                                @error('email') <span class="text-sm text-red-700">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-2 text-sm font-medium text-gray-800">Phone
                                <input type="tel" wire:model="phone" class="rounded-lg border-gray-300 text-gray-950 focus:border-amber-600 focus:ring-amber-600" />
                                @error('phone') <span class="text-sm text-red-700">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-2 text-sm font-medium text-gray-800 sm:col-span-2">Address
                                <textarea wire:model="address" rows="3" class="rounded-lg border-gray-300 text-gray-950 focus:border-amber-600 focus:ring-amber-600"></textarea>
                                @error('address') <span class="text-sm text-red-700">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-2 text-sm font-medium text-gray-800 sm:col-span-2">Timezone
                                <select wire:model="timezone" class="rounded-lg border-gray-300 text-gray-950 focus:border-amber-600 focus:ring-amber-600">
                                    @foreach (timezone_identifiers_list() as $timezoneOption)
                                        <option value="{{ $timezoneOption }}">{{ $timezoneOption }}</option>
                                    @endforeach
                                </select>
                                @error('timezone') <span class="text-sm text-red-700">{{ $message }}</span> @enderror
                            </label>
                        </div>
                        <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <x-filament::button color="gray" type="button" wire:click="exitSetup">Exit setup</x-filament::button>
                            <x-filament::button type="submit">Save and continue</x-filament::button>
                        </div>
                    </form>
                    <aside class="rounded-2xl border border-gray-200 bg-gray-50 p-6">
                        <x-filament::icon icon="heroicon-o-shield-check" class="h-7 w-7 text-amber-700" />
                        <h3 class="mt-4 font-semibold text-gray-950">Commercial identity is protected</h3>
                        <dl class="mt-5 space-y-4 text-sm">
                            <div><dt class="text-gray-500">Operating country</dt><dd class="mt-1 font-medium text-gray-900">{{ $this->getViewData()['state']->church?->operating_country_code ?? app(App\Support\TenantContext::class)->currentChurch()->operating_country_code }}</dd></div>
                            <div><dt class="text-gray-500">Church address</dt><dd class="mt-1 font-medium text-gray-900">{{ app(App\Support\TenantContext::class)->currentChurch()->slug }}.keryon.app</dd></div>
                        </dl>
                        <p class="mt-5 text-xs leading-5 text-gray-600">Country and Church address are read-only here because they affect governed commercial and public URL identity.</p>
                    </aside>
                </section>
            @elseif ($step === App\Enums\ChurchOnboardingStep::BRAND)
                <section class="grid gap-6 lg:grid-cols-[1.25fr_0.75fr]">
                    <div class="rounded-2xl border border-gray-200 bg-white p-7 sm:p-9">
                        <x-filament::icon icon="heroicon-o-swatch" class="h-9 w-9 text-amber-700" />
                        <h3 class="mt-6 text-2xl font-semibold tracking-tight text-gray-950">Bring your church identity into Keryon</h3>
                        <p class="mt-3 max-w-xl text-sm leading-6 text-gray-600">Add a logo, mark, colors, and type choices through the existing rights-aware Brand workspace. Every field is optional.</p>
                        <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                            <x-filament::button tag="a" :href="$brandUrl">Open Brand settings</x-filament::button>
                            <x-filament::button color="gray" wire:click="skip">{{ $brandConfigured ? 'Continue' : 'Skip brand' }}</x-filament::button>
                        </div>
                    </div>
                    <aside class="rounded-2xl border border-gray-200 bg-gray-950 p-7 text-gray-100">
                        <p class="text-sm font-medium text-amber-300">{{ $brandConfigured ? 'Brand started' : 'Optional step' }}</p>
                        <p class="mt-4 text-lg font-semibold">Logo assets stay private until an authorized Website publication.</p>
                        <p class="mt-3 text-sm leading-6 text-gray-300">Uploads continue through Institutional Media with existing ownership and rights declarations.</p>
                    </aside>
                </section>
            @elseif ($step === App\Enums\ChurchOnboardingStep::SERVICE_TIMES)
                <form wire:submit="saveServiceTimes" class="rounded-2xl border border-gray-200 bg-white p-6 sm:p-8">
                    <div class="max-w-2xl"><h3 class="text-xl font-semibold text-gray-950">Add regular service times</h3><p class="mt-2 text-sm leading-6 text-gray-600">Add one or several services, or continue without any. Times use your Church timezone.</p></div>
                    <div class="mt-7 space-y-4">
                        @foreach ($serviceTimes as $index => $serviceTime)
                            <fieldset class="grid gap-4 rounded-xl border border-gray-200 bg-gray-50 p-4 sm:grid-cols-[1fr_0.7fr_0.7fr_auto]" wire:key="service-time-{{ $index }}">
                                <legend class="sr-only">Service time {{ $index + 1 }}</legend>
                                <label class="grid gap-2 text-sm font-medium text-gray-800">Service name<input wire:model="serviceTimes.{{ $index }}.label" class="rounded-lg border-gray-300" placeholder="Sunday Service" /></label>
                                <label class="grid gap-2 text-sm font-medium text-gray-800">Day<select wire:model="serviceTimes.{{ $index }}.day_of_week" class="rounded-lg border-gray-300">@foreach ($dayOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                                <label class="grid gap-2 text-sm font-medium text-gray-800">Time<input wire:model="serviceTimes.{{ $index }}.time" class="rounded-lg border-gray-300" placeholder="9:00 AM" /></label>
                                <button type="button" wire:click="removeServiceTime({{ $index }})" class="self-end rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-600">Remove</button>
                            </fieldset>
                        @endforeach
                        @error('serviceTimes.*') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="addServiceTime" class="mt-4 rounded-lg px-3 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-600">Add another service</button>
                    <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                        <x-filament::button color="gray" type="button" wire:click="exitSetup">Exit setup</x-filament::button>
                        <x-filament::button type="submit">{{ collect($serviceTimes)->contains(fn ($row) => filled($row['label']) || filled($row['time'])) ? 'Save and continue' : 'Skip service times' }}</x-filament::button>
                    </div>
                </form>
            @elseif ($step === App\Enums\ChurchOnboardingStep::DIGITAL_PRESENCE)
                <section class="space-y-6">
                    <form wire:submit="saveDigitalPresence" class="rounded-2xl border border-gray-200 bg-white p-6 sm:p-8">
                        <h3 class="text-xl font-semibold text-gray-950">Connect your public channels</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Social links are optional and remain Church-owned institutional information.</p>
                        <div class="mt-7 space-y-4">
                            @foreach ($socialLinks as $index => $socialLink)
                                <fieldset class="grid gap-4 rounded-xl border border-gray-200 bg-gray-50 p-4 sm:grid-cols-[0.55fr_1fr_auto]" wire:key="social-link-{{ $index }}">
                                    <legend class="sr-only">Social link {{ $index + 1 }}</legend>
                                    <label class="grid gap-2 text-sm font-medium text-gray-800">Platform<select wire:model="socialLinks.{{ $index }}.platform" class="rounded-lg border-gray-300">@foreach ($socialOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                                    <label class="grid gap-2 text-sm font-medium text-gray-800">URL<input type="url" wire:model="socialLinks.{{ $index }}.url" class="rounded-lg border-gray-300" placeholder="https://" /></label>
                                    <button type="button" wire:click="removeSocialLink({{ $index }})" class="self-end rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-600">Remove</button>
                                </fieldset>
                            @endforeach
                            @error('socialLinks.*') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <button type="button" wire:click="addSocialLink" class="mt-4 rounded-lg px-3 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-600">Add another link</button>
                        <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><x-filament::button type="submit">{{ collect($socialLinks)->contains(fn ($row) => filled($row['url'])) ? 'Save and continue' : 'Skip social links' }}</x-filament::button></div>
                    </form>
                    <div class="grid gap-5 md:grid-cols-[1.2fr_0.8fr]">
                        <div class="rounded-2xl border border-gray-200 bg-white p-6"><x-filament::icon icon="heroicon-o-globe-alt" class="h-7 w-7 text-amber-700" /><h3 class="mt-4 font-semibold text-gray-950">Set up your Website when ready</h3><p class="mt-2 text-sm leading-6 text-gray-600">Keryon can power your Church Website. Opening Website does not publish or create public copy.</p><a href="{{ $websiteUrl }}" class="mt-5 inline-flex text-sm font-semibold text-amber-800 hover:text-amber-900">Open Website</a></div>
                        <a href="{{ \App\Filament\Pages\ChurchStaffAccess::getUrl() }}" class="block rounded-2xl border border-gray-300 bg-gray-50 p-6 transition hover:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-600"><x-filament::icon icon="heroicon-o-user-plus" class="h-7 w-7 text-gray-500" /><h3 class="mt-4 font-semibold text-gray-900">Invite your team</h3><p class="mt-2 text-sm leading-6 text-gray-600">Set deliberate Church roles and keep Care access explicit.</p></a>
                    </div>
                </section>
            @elseif ($step === App\Enums\ChurchOnboardingStep::COMPLETE)
                <section class="grid overflow-hidden rounded-2xl border border-gray-200 bg-white lg:grid-cols-[1.2fr_0.8fr]">
                    <div class="p-7 sm:p-10"><x-filament::icon icon="heroicon-o-check-circle" class="h-10 w-10 text-amber-700" /><h3 class="mt-6 text-3xl font-semibold tracking-tight text-gray-950">Your Church is ready</h3><p class="mt-3 max-w-xl text-base leading-7 text-gray-600">Optional details can be changed anytime. Finish setup and continue into the normal Keryon workspace.</p><div class="mt-8"><x-filament::button size="lg" wire:click="finish">Finish setup</x-filament::button></div></div>
                    <aside class="border-t border-gray-200 bg-gray-50 p-7 sm:p-10 lg:border-l lg:border-t-0"><h4 class="font-semibold text-gray-950">Ready now</h4><dl class="mt-5 space-y-4 text-sm"><div><dt class="text-gray-500">Church identity</dt><dd class="mt-1 font-medium text-gray-900">Confirmed</dd></div><div><dt class="text-gray-500">Brand</dt><dd class="mt-1 font-medium text-gray-900">{{ $brandConfigured ? 'Started' : 'Available later' }}</dd></div><div><dt class="text-gray-500">Service times</dt><dd class="mt-1 font-medium text-gray-900">{{ $serviceTimeCount ?: 'Available later' }}</dd></div><div><dt class="text-gray-500">Social links</dt><dd class="mt-1 font-medium text-gray-900">{{ $socialLinkCount ?: 'Available later' }}</dd></div></dl></aside>
                </section>
            @endif

            @if ($state->status === $statuses::IN_PROGRESS && $step !== App\Enums\ChurchOnboardingStep::COMPLETE)
                <div class="flex justify-center pt-2"><button type="button" wire:click="dismiss" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-amber-600">Finish for now</button></div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
