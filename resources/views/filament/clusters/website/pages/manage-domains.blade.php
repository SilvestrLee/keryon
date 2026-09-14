<x-filament-panels::page>
    <div class="mx-auto w-full max-w-6xl space-y-8 overflow-x-hidden">
        <header class="max-w-3xl">
            <h1 class="text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">Your Church Website addresses</h1>
            <p class="mt-2 text-sm leading-6 text-gray-600 sm:text-base">Connect a domain your Church already owns. Keryon keeps your permanent address available as a reliable fallback.</p>
        </header>

        <section class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]" aria-label="Website addresses">
            <article class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800">
                        <x-filament::icon icon="heroicon-o-star" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-amber-950">Official website address</h2>
                        <p class="mt-2 break-all text-lg font-semibold text-gray-950 sm:text-xl">{{ $officialUrl ?? $keryonUrl }}</p>
                        <p class="mt-2 text-sm leading-6 text-amber-950/75">
                            @if ($isPublished)
                                This is the address Keryon currently treats as official.
                            @else
                                This address will become public after your Website is published.
                            @endif
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if ($officialUrl)
                                <a href="{{ $officialUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center rounded-xl bg-amber-800 px-4 text-sm font-semibold text-white hover:bg-amber-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-700 focus-visible:ring-offset-2">Visit website</a>
                            @endif
                            <button type="button" x-data x-on:click="navigator.clipboard.writeText(@js($officialUrl ?? $keryonUrl))" aria-label="Copy official website address" class="inline-flex min-h-10 items-center rounded-xl border border-amber-300 bg-white px-4 text-sm font-semibold text-amber-950 hover:bg-amber-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-700 focus-visible:ring-offset-2">Copy address</button>
                        </div>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-950">Your Keryon address</h2>
                <p class="mt-2 break-all text-base font-semibold text-gray-900">{{ $keryonUrl }}</p>
                <p class="mt-2 text-sm leading-6 text-gray-600">Your Keryon address remains available even when you connect a custom domain.</p>
                <button type="button" x-data x-on:click="navigator.clipboard.writeText(@js($keryonUrl))" aria-label="Copy permanent Keryon address" class="mt-4 inline-flex min-h-10 items-center rounded-xl border border-gray-300 px-4 text-sm font-semibold text-gray-800 hover:border-amber-500 hover:text-amber-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-700 focus-visible:ring-offset-2">Copy address</button>
            </article>
        </section>

        @unless ($isPublished)
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5" role="note">
                <h2 class="font-semibold text-gray-950">Your Website is not published yet</h2>
                <p class="mt-1 text-sm leading-6 text-gray-600">You can configure a domain now, but your Website will not be publicly visible until an authorized person publishes it.</p>
            </div>
        @endunless

        @if ($domains->isEmpty())
            @if ($customDomainAllowed)
                <section class="rounded-2xl border border-gray-200 bg-white p-6 sm:p-8" aria-labelledby="connect-domain-heading">
                    <div class="max-w-2xl">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-700"><x-filament::icon icon="heroicon-o-link" class="h-6 w-6" /></span>
                        <h2 id="connect-domain-heading" class="mt-5 text-xl font-semibold text-gray-950">Connect your domain</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Use a domain your Church already owns for your Keryon Website. Your Keryon address will continue working as a fallback.</p>
                    </div>
                    @include('filament.clusters.website.pages.partials.domain-claim-form')
                </section>
            @else
                <section class="rounded-2xl border border-gray-200 bg-gray-50 p-6 sm:p-8" aria-labelledby="custom-domain-unavailable-heading">
                    <div class="max-w-2xl">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-700"><x-filament::icon icon="heroicon-o-link" class="h-6 w-6" /></span>
                        <h2 id="custom-domain-unavailable-heading" class="mt-5 text-xl font-semibold text-gray-950">Custom domains aren't included in your current plan</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Your Keryon Church address remains available.</p>
                    </div>
                </section>
            @endif
        @else
            <section aria-labelledby="custom-domains-heading">
                <div>
                    <h2 id="custom-domains-heading" class="text-xl font-semibold text-gray-950">Custom domains</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-600">Connection status is based on stored verification evidence. Opening this page never performs a DNS check.</p>
                </div>

                <div class="mt-5 space-y-5">
                    @foreach ($domains as $domain)
                        @php($steps = $presenter->steps($domain))
                        <article wire:key="domain-{{ $domain->id }}" class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6" aria-labelledby="domain-{{ $domain->id }}-heading">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 id="domain-{{ $domain->id }}-heading" class="break-all text-lg font-semibold text-gray-950">{{ $domain->normalized_hostname }}</h3>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $domain->status === \App\Enums\DomainStatus::Active ? 'bg-emerald-50 text-emerald-800' : ($domain->status === \App\Enums\DomainStatus::Degraded ? 'bg-red-50 text-red-800' : 'bg-gray-100 text-gray-700') }}">{{ $presenter->statusLabel($domain) }}</span>
                                        @if ($domain->is_primary)
                                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">Primary domain</span>
                                        @elseif ($domain->isEligible())
                                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">Companion domain</span>
                                        @endif
                                    </div>
                                    @if ($domain->last_checked_at)
                                        <p class="mt-2 text-xs text-gray-500">Last checked {{ $domain->last_checked_at->diffForHumans() }}</p>
                                    @endif
                                </div>
                            </div>

                            <ol class="mt-6 grid gap-2 sm:grid-cols-5" aria-label="Domain readiness">
                                @foreach ($steps as $step)
                                    <li class="flex min-h-12 items-center gap-2 rounded-xl px-3 py-2 text-xs font-medium {{ $step['complete'] ? 'bg-emerald-50 text-emerald-800' : 'bg-gray-50 text-gray-500' }}">
                                        <x-filament::icon :icon="$step['complete'] ? 'heroicon-o-check-circle' : 'heroicon-o-clock'" class="h-4 w-4 shrink-0" />
                                        <span>{{ $step['label'] }}</span>
                                    </li>
                                @endforeach
                            </ol>

                            <div class="mt-6 grid gap-4 md:grid-cols-3">
                                <div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-medium text-gray-500">Ownership</p><p class="mt-1 text-sm font-semibold text-gray-900">{{ $domain->ownership_verified_at ? 'Verified' : 'Waiting for verification' }}</p></div>
                                <div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-medium text-gray-500">Website connection</p><p class="mt-1 text-sm font-semibold text-gray-900">{{ $domain->routing_verified_at ? 'Connected' : 'Waiting for DNS' }}</p></div>
                                <div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-medium text-gray-500">HTTPS</p><p class="mt-1 text-sm font-semibold text-gray-900">{{ $presenter->tlsLabel($domain) }}</p></div>
                            </div>

                            @if ($message = $presenter->failureMessage($domain))
                                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950" role="status">{{ $message }}</div>
                            @endif

                            @if ($domain->status === \App\Enums\DomainStatus::Degraded)
                                <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4" role="alert"><p class="font-semibold text-red-950">Custom domain needs attention</p><p class="mt-1 text-sm leading-6 text-red-900">Your Website content is safe. Keryon is using <span class="break-all font-semibold">{{ $keryonUrl }}</span> as the official fallback.</p></div>
                            @endif

                            @if (! $domain->ownership_verified_at)
                                <section class="mt-6 rounded-xl border border-gray-200 p-4 sm:p-5" aria-labelledby="ownership-{{ $domain->id }}">
                                    <h4 id="ownership-{{ $domain->id }}" class="font-semibold text-gray-950">Prove you control this domain</h4>
                                    <p class="mt-1 text-sm leading-6 text-gray-600">Add this TXT record where your Church manages its DNS records.</p>
                                    <dl class="mt-4 grid gap-3 sm:grid-cols-[7rem_minmax(0,1fr)]">
                                        <dt class="text-xs font-semibold text-gray-500">Type</dt><dd class="text-sm font-medium text-gray-900">TXT</dd>
                                        <dt class="text-xs font-semibold text-gray-500">Name</dt><dd class="min-w-0 break-all rounded-lg bg-gray-50 p-3 font-mono text-xs text-gray-800">_keryon-verification.{{ $domain->normalized_hostname }}</dd>
                                        <dt class="text-xs font-semibold text-gray-500">Value</dt>
                                        <dd class="min-w-0">
                                            @if ($verificationDomainId === $domain->id && $verificationToken)
                                                <div class="break-all rounded-lg bg-gray-950 p-3 font-mono text-xs leading-5 text-white" data-testid="verification-token">{{ $verificationToken }}</div>
                                                <button type="button" x-data x-on:click="navigator.clipboard.writeText(@js($verificationToken))" aria-label="Copy DNS verification value" class="mt-2 text-sm font-semibold text-amber-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-700">Copy verification value</button>
                                            @else
                                                <p class="text-sm leading-6 text-gray-600">For security, the verification value is not stored by Keryon after it is shown.</p>
                                                @if ($presenter->canRegenerate($domain) && $customDomainAllowed)
                                                    <button type="button" wire:click="regenerate({{ $domain->id }})" wire:confirm="Generate a new verification value? The previous value will no longer work, and you will need to update the TXT record." class="mt-3 inline-flex min-h-10 items-center rounded-xl border border-gray-300 px-4 text-sm font-semibold text-gray-800 hover:border-amber-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-700">Generate new verification value</button>
                                                @endif
                                            @endif
                                        </dd>
                                    </dl>
                                </section>
                            @endif

                            @if (! $domain->routing_verified_at)
                                <section class="mt-4 rounded-xl border border-gray-200 p-4 sm:p-5" aria-label="Website connection instructions">
                                    <h4 class="font-semibold text-gray-950">Connect Website traffic</h4>
                                    @if ($routingInstructions)
                                        <p class="mt-1 text-sm leading-6 text-gray-600">Add the routing record below at your DNS provider.</p>
                                        <dl class="mt-4 grid gap-3 sm:grid-cols-[7rem_minmax(0,1fr)]"><dt class="text-xs font-semibold text-gray-500">Type</dt><dd class="text-sm font-medium">{{ $routingInstructions['type'] }}</dd><dt class="text-xs font-semibold text-gray-500">Name</dt><dd class="break-all text-sm">{{ $domain->normalized_hostname }}</dd><dt class="text-xs font-semibold text-gray-500">Value</dt><dd class="break-all rounded-lg bg-gray-50 p-3 font-mono text-xs">{{ $routingInstructions['value'] }}</dd></dl>
                                        <p class="mt-3 text-xs leading-5 text-gray-500">Root domains may require A, AAAA, ALIAS, or flattening support from your DNS provider. Keryon only marks a connection ready after configured infrastructure confirms it.</p>
                                    @else
                                        <p class="mt-1 text-sm leading-6 text-gray-600">Connection details are awaiting Keryon's production infrastructure. Custom-domain activation is not yet available in this environment.</p>
                                    @endif
                                </section>
                            @endif

                            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                @if ($connectionAvailable && ! $domain->isEligible() && $customDomainAllowed)
                                    <button type="button" wire:click="verify({{ $domain->id }})" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-amber-800 px-4 text-sm font-semibold text-white hover:bg-amber-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-700 focus-visible:ring-offset-2">Check connection</button>
                                @endif
                                @if ($domain->isEligible() && ! $domain->is_primary && $customDomainAllowed)
                                    <button type="button" wire:click="makePrimary({{ $domain->id }})" wire:confirm="Make {{ $domain->normalized_hostname }} your official Website address? Your Keryon address will continue to work as a fallback." class="inline-flex min-h-10 items-center justify-center rounded-xl bg-amber-800 px-4 text-sm font-semibold text-white hover:bg-amber-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-700 focus-visible:ring-offset-2">Make primary</button>
                                @endif
                                @unless (in_array($domain->status, [\App\Enums\DomainStatus::Disabled, \App\Enums\DomainStatus::Released], true))
                                    <button type="button" wire:click="disable({{ $domain->id }})" wire:confirm="Disable this domain? It will stop serving your Website through Keryon. Your content and Keryon address will remain available." class="inline-flex min-h-10 items-center justify-center rounded-xl border border-gray-300 px-4 text-sm font-semibold text-gray-800 hover:border-red-400 hover:text-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700">Disable</button>
                                @endunless
                                @if ($domain->status !== \App\Enums\DomainStatus::Released)
                                    <button type="button" wire:click="release({{ $domain->id }})" wire:confirm="Release this domain? It will be disconnected from this Church. Keryon will retain its domain history, and released domains cannot currently be reclaimed through self-service." class="inline-flex min-h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700">Release domain</button>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($canAddDomain && $customDomainAllowed)
                    <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-5 sm:p-6">
                        <h3 class="font-semibold text-gray-950">Add a companion address</h3>
                        <p class="mt-1 text-sm leading-6 text-gray-600">Keryon supports one primary custom domain and one companion address.</p>
                        @include('filament.clusters.website.pages.partials.domain-claim-form')
                    </div>
                @elseif (! $customDomainAllowed)
                    <p class="mt-5 rounded-xl bg-gray-50 p-4 text-sm text-gray-600">Custom domains aren't included in your current plan. Your Keryon Church address remains available.</p>
                @else
                    <p class="mt-5 rounded-xl bg-gray-50 p-4 text-sm text-gray-600">Keryon currently supports one primary custom domain and one companion address.</p>
                @endif
            </section>
        @endif

        @if ($releasedDomains->isNotEmpty())
            <details class="rounded-2xl border border-gray-200 bg-white p-5">
                <summary class="cursor-pointer font-semibold text-gray-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-700">Previous domains ({{ $releasedDomains->count() }})</summary>
                <div class="mt-4 space-y-3">
                    @foreach ($releasedDomains as $domain)
                        <div class="rounded-xl bg-gray-50 p-4"><p class="break-all text-sm font-semibold text-gray-800">{{ $domain->normalized_hostname }}</p><p class="mt-1 text-xs text-gray-500">Released {{ $domain->released_at?->format('j M Y, H:i') }}. Disconnected from this Church; Keryon retains its history. Not currently reclaimable through self-service.</p></div>
                    @endforeach
                </div>
            </details>
        @endif

        <section class="rounded-2xl bg-gray-50 p-5 sm:p-6" aria-labelledby="domain-help-heading">
            <h2 id="domain-help-heading" class="font-semibold text-gray-950">Domain help</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div><h3 class="text-sm font-semibold text-gray-900">Where do I change DNS records?</h3><p class="mt-1 text-sm leading-6 text-gray-600">Use the service where your Church manages its domain. Do not remove email, MX, SPF, or DKIM records.</p></div>
                <div><h3 class="text-sm font-semibold text-gray-900">Why is verification taking time?</h3><p class="mt-1 text-sm leading-6 text-gray-600">DNS changes can take time to appear. Check the values carefully, then run another connection check.</p></div>
                <div><h3 class="text-sm font-semibold text-gray-900">Will my Keryon address stop working?</h3><p class="mt-1 text-sm leading-6 text-gray-600">No. Your permanent Keryon address remains available as a fallback.</p></div>
                <div><h3 class="text-sm font-semibold text-gray-900">Why is HTTPS required?</h3><p class="mt-1 text-sm leading-6 text-gray-600">Keryon requires a secure HTTPS connection before a custom domain can become official.</p></div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
