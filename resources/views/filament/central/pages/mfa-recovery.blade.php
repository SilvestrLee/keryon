<x-filament-panels::page>
    <main class="central"><header class="central-page-head"><div><p class="central-eyebrow">Governed security operation</p><h1>Reset platform MFA</h1><p>Use only after identity verification and security escalation. The old authenticator and every recovery code will be destroyed.</p></div></header>
        <form wire:submit="resetMfa" class="central-operation-form">
            <label>Target verified email<input type="email" wire:model="targetEmail" autocomplete="off">@error('targetEmail')<span>{{ $message }}</span>@enderror</label>
            <label>Reason category<select wire:model="reason">@foreach(\App\Enums\PlatformAuditReasonCategory::cases() as $category)<option value="{{ $category->value }}">{{ str($category->value)->replace('_', ' ')->headline() }}</option>@endforeach</select></label>
            <label>Required note<textarea wire:model="note" maxlength="500" rows="3"></textarea>@error('note')<span>{{ $message }}</span>@enderror</label>
            <label>Confirm your password<input type="password" wire:model="password" autocomplete="current-password">@error('password')<span>{{ $message }}</span>@enderror</label>
            <button class="central-button central-button--danger" type="submit">Reset MFA credentials</button>
        </form>
    </main>
</x-filament-panels::page>
