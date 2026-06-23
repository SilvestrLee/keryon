<x-filament-panels::page>
    <div class="max-w-lg mx-auto py-8">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 space-y-6">

            <div>
                <h2 class="text-xl font-bold text-gray-900">Welcome to Keryon</h2>
                <p class="text-sm text-gray-500 mt-1">Let's set up your church before you get started.</p>
            </div>

            <form wire:submit.prevent="save" class="space-y-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Church Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="name"
                        placeholder="e.g. Grace Community Church"
                        class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1E5631]"
                    />
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Church Email</label>
                    <input
                        type="email"
                        wire:model="email"
                        placeholder="office@yourchurch.org"
                        class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1E5631]"
                    />
                    @error('email')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Timezone <span class="text-red-500">*</span>
                    </label>
                    <select
                        wire:model="timezone"
                        class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1E5631]"
                    >
                        @foreach (timezone_identifiers_list() as $tz)
                            <option value="{{ $tz }}" @selected($timezone === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                    @error('timezone')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full rounded-xl bg-[#1E5631] text-white py-2.5 px-4 text-sm font-semibold hover:bg-[#174a29] transition disabled:opacity-60"
                >
                    <span wire:loading.remove>Create My Church</span>
                    <span wire:loading>Setting up...</span>
                </button>

            </form>

        </div>
    </div>
</x-filament-panels::page>
