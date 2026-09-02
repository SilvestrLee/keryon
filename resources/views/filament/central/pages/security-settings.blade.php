<x-filament-panels::page>
    <main class="central central-security-settings">
        <header class="central-page-head"><div><p class="central-eyebrow">Account security</p><h1>Multi-factor authentication</h1><p>Keryon Central contains privileged platform operations. Multi-factor authentication protects churches and Keryon operations.</p></div></header>
        <section class="central-section"><div class="central-section__head"><h2>Authenticator app</h2><p><strong>Enabled.</strong> Central challenges expire after 8 hours or 30 minutes of inactivity.</p></div><a class="central-back" href="{{ \App\Filament\Central\Pages\MfaChallenge::getUrl(panel: 'central') }}">Verify again</a></section>
        <section class="central-section"><div class="central-section__head"><h2>Recovery codes</h2><p>Regeneration invalidates every previous code and all stale Central MFA sessions. Fresh MFA and your current password are required.</p></div>
            @if($newRecoveryCodes)
                <div class="central-recovery" role="status"><h3>Save these codes now</h3><p>Each code works once. They cannot be shown again.</p><pre>{{ implode("\n", $newRecoveryCodes) }}</pre></div>
            @else
                <form wire:submit="regenerate" class="central-operation-form"><label for="security-password">Confirm your password</label><input id="security-password" type="password" wire:model="password" autocomplete="current-password">@error('password')<span class="central-operation-error">{{ $message }}</span>@enderror<button class="central-button" type="submit">Regenerate recovery codes</button></form>
            @endif
        </section>
    </main>
</x-filament-panels::page>
