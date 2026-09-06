<x-filament-panels::page>
    <p class="text-sm text-gray-500">
        Choose which optional pages appear on your website, and the order they show in your navigation menu.
        Home and Contact always appear and cannot be turned off.
    </p>

    <form wire:submit="save" class="mt-4">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit">
                Save
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
