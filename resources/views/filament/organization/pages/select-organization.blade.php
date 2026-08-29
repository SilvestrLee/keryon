<x-filament-panels::page>
    <p class="org-intro">Choose the Organization workspace you intend to administer. This selection does not change your active Church.</p>

    <div class="org-selector">
        @foreach ($this->memberships() as $membership)
            <button type="button" wire:click="selectOrganization({{ $membership->organization_id }})">
                <strong>{{ $membership->organization->name }}</strong>
                <span>Active Organization membership</span>
            </button>
        @endforeach
    </div>
</x-filament-panels::page>
