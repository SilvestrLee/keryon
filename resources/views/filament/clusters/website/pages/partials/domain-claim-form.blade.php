<form wire:submit="claim" class="mt-6 max-w-xl">
    <label for="custom-domain" class="block text-sm font-semibold text-gray-900">Custom domain</label>
    <div class="mt-2 flex flex-col gap-2 sm:flex-row">
        <input id="custom-domain" type="text" wire:model="hostname" autocomplete="url" placeholder="www.yourchurch.org" aria-describedby="custom-domain-help" class="min-h-11 min-w-0 flex-1 rounded-xl border-gray-300 text-sm shadow-none focus:border-amber-600 focus:ring-amber-600" />
        <button type="submit" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-amber-800 px-5 text-sm font-semibold text-white hover:bg-amber-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-700 focus-visible:ring-offset-2">Connect a domain</button>
    </div>
    <p id="custom-domain-help" class="mt-2 text-xs leading-5 text-gray-500">Enter only the domain name, without https://, a path, or a port.</p>
    @error('hostname') <p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p> @enderror
</form>
