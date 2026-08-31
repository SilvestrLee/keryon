<x-filament-panels::page>
    <main class="mx-auto w-full max-w-6xl space-y-6" aria-labelledby="handoff-heading">
        <header class="rounded-2xl bg-[#132E35] px-5 py-6 text-white sm:px-7">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#F0BD6B]">Communications to Website</p>
            <h1 id="handoff-heading" class="mt-2 text-2xl font-semibold sm:text-3xl">Prepare a Website draft</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-white/75">Choose where this approved content belongs. This updates working Website content and never publishes it.</p>
        </header>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <section class="rounded-2xl border border-gray-200 bg-white p-5" aria-labelledby="source-heading">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Approved source</p>
                <h2 id="source-heading" class="mt-2 text-lg font-semibold text-gray-950">{{ $source->title }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $source->content_type->label() }} · Approved {{ $source->approved_at->format('j M Y, H:i') }}</p>
                <div class="mt-5 max-h-72 overflow-y-auto rounded-xl bg-gray-50 p-4 text-sm leading-6 text-gray-700">{!! Illuminate\Support\Str::markdown($source->body) !!}</div>
                <a href="{{ $contentUrl }}" class="mt-4 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-[#9A5A00] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E09F3E]" wire:navigate>View in Content Studio<x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" aria-hidden="true" /></a>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6" aria-labelledby="destination-heading">
                <h2 id="destination-heading" class="text-lg font-semibold text-gray-950">Website destination</h2>
                <p class="mt-1 text-sm text-gray-600">Only destinations compatible with this Content type are available.</p>

                <label class="mt-5 block" for="destination"><span class="text-sm font-semibold text-gray-800">Destination</span>
                    <select id="destination" wire:model.live="destination" class="mt-2 block min-h-11 w-full rounded-xl border-gray-300 text-sm focus:border-[#E09F3E] focus:ring-[#E09F3E]">
                        <option value="">Choose a destination</option>
                        @foreach ($destinations as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                    </select>
                </label>
                @error('destination')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror

                @if ($selectedDestination)
                    <div class="mt-5 rounded-xl bg-[#FFF8EC] p-4"><p class="text-sm font-semibold text-[#744400]">{{ $selectedDestination->label() }}</p><p class="mt-1 text-sm leading-6 text-[#744400]">{{ $selectedDestination->description() }}</p></div>

                    @if ($selectedDestination->supportsMedia() && $eligibleMedia->isNotEmpty())
                        <label class="mt-5 block" for="media"><span class="text-sm font-semibold text-gray-800">Approved design image (optional)</span>
                            <select id="media" wire:model="media" class="mt-2 block min-h-11 w-full rounded-xl border-gray-300 text-sm focus:border-[#E09F3E] focus:ring-[#E09F3E]"><option value="">Keep text only</option>@foreach($eligibleMedia as $asset)<option value="{{ $asset->id }}">{{ $asset->original_filename }}</option>@endforeach</select>
                        </label>
                    @endif

                    <div class="mt-5" aria-labelledby="mapping-preview"><h3 id="mapping-preview" class="text-sm font-semibold text-gray-900">Mapping preview</h3><dl class="mt-3 space-y-3">@foreach($mapped as $field => $value)<div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ str_replace('_', ' ', $field) }}</dt><dd class="mt-1 whitespace-pre-line text-sm leading-6 text-gray-700">{{ is_string($value) ? $value : 'Selected governed Media' }}</dd></div>@endforeach</dl></div>

                    @if ($destinationState['populated'])
                        <div class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4" role="note"><h3 class="text-sm font-semibold text-amber-950">This destination already has content</h3><p class="mt-1 text-sm leading-6 text-amber-900">Review the mapping carefully. Website edits are never replaced silently.</p>
                            <label class="mt-3 flex gap-3 text-sm text-amber-950"><input type="checkbox" wire:model="replace" class="mt-0.5 rounded border-amber-400 text-[#B66A00] focus:ring-[#E09F3E]" /><span>I have reviewed this destination and want to replace its mapped fields.</span></label>
                        </div>
                    @endif

                    @if ($destinationState['provenance'])
                        <div class="mt-4 text-xs leading-5 text-gray-500">Current source: {{ $destinationState['provenance']->contentItem?->title }}@if($destinationState['provenance']->campaign) in {{ $destinationState['provenance']->campaign->title }}@endif. Applied by {{ $destinationState['provenance']->actor?->name }}.</div>
                    @endif

                    <button type="button" wire:click="apply" class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-[#B66A00] px-5 text-sm font-semibold text-white transition hover:bg-[#955700] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E09F3E] focus-visible:ring-offset-2 sm:w-auto">Apply to Website draft</button>
                @endif
            </section>
        </div>
    </main>
</x-filament-panels::page>
