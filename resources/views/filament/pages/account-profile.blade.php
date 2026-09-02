<x-filament-panels::page>
    <div class="ks-profile">
        <header>
            <p>Personal account</p>
            <h2>Identity and sign-in</h2>
            <span>Your personal account is separate from Church, Organization, and Platform settings.</span>
        </header>

        <section aria-labelledby="ks-profile-identity">
            <div>
                <h3 id="ks-profile-identity">My identity</h3>
                <p>Update the name shown across your Keryon workspaces.</p>
            </div>
            <form wire:submit="saveIdentity">
                <label for="ks-profile-name">Name</label>
                <input id="ks-profile-name" type="text" wire:model="name" autocomplete="name" required maxlength="255">
                @error('name') <p class="ks-profile__error">{{ $message }}</p> @enderror
                <label for="ks-profile-email">Verified email</label>
                <input id="ks-profile-email" type="email" value="{{ $email }}" autocomplete="email" disabled>
                <p class="ks-profile__hint">Email changes require a separate verified identity-change workflow.</p>
                <x-filament::button type="submit">Save profile</x-filament::button>
            </form>
        </section>

        <section aria-labelledby="ks-profile-password">
            <div>
                <h3 id="ks-profile-password">Password</h3>
                <p>Use your current password to set a new one.</p>
            </div>
            <form wire:submit="changePassword">
                <label for="ks-current-password">Current password</label>
                <input id="ks-current-password" type="password" wire:model="currentPassword" autocomplete="current-password" required>
                @error('currentPassword') <p class="ks-profile__error">{{ $message }}</p> @enderror
                <label for="ks-new-password">New password</label>
                <input id="ks-new-password" type="password" wire:model="password" autocomplete="new-password" required>
                @error('password') <p class="ks-profile__error">{{ $message }}</p> @enderror
                <label for="ks-confirm-password">Confirm new password</label>
                <input id="ks-confirm-password" type="password" wire:model="passwordConfirmation" autocomplete="new-password" required>
                <x-filament::button type="submit">Change password</x-filament::button>
            </form>
        </section>
    </div>
</x-filament-panels::page>
