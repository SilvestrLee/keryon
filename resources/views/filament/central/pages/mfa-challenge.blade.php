<x-filament-panels::page>
    <main class="central-security">
        <p class="central-eyebrow">Privileged access</p>
        <h1>Verify your identity</h1>
        <p>Keryon Central contains privileged platform operations. Enter {{ $useRecoveryCode ? 'one unused recovery code' : 'the current code from your authenticator app' }} to continue.</p>
        <form wire:submit="verify" class="central-operation-form">
            <label for="mfa-code">{{ $useRecoveryCode ? 'Recovery code' : '6-digit authentication code' }}</label>
            <input id="mfa-code" wire:model="code" {{ $useRecoveryCode ? 'autocomplete=off' : 'inputmode=numeric autocomplete=one-time-code' }} autofocus>
            @error('code')<span class="central-operation-error" role="alert">{{ $message }}</span>@enderror
            <button class="central-button" type="submit">Verify</button>
        </form>
        <button class="central-back" type="button" wire:click="toggleRecovery">{{ $useRecoveryCode ? 'Use authenticator code' : 'Use a recovery code' }}</button>
    </main>
</x-filament-panels::page>
