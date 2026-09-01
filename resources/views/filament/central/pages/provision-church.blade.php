<x-filament-panels::page>
    <main class="central central-read">
        <header class="central-page-head"><div><p class="central-eyebrow">Governed operation</p><h1>Provision Church</h1><p>Create an inactive Church and its Primary activation through the canonical onboarding service.</p></div></header>
        <form wire:submit="provision" class="central-operation-form" aria-describedby="provision-consequence">
            <section><h2>Church and Primary</h2><p id="provision-consequence">No customer membership is created. The Church remains inactive until its Primary accepts the activation.</p><div class="central-operation-fields">
                <label>Church name<input wire:model="churchName" autocomplete="organization">@error('churchName')<span>{{ $message }}</span>@enderror</label>
                <label>Requested slug <small>Optional</small><input wire:model="requestedSlug" autocomplete="off">@error('requestedSlug')<span>{{ $message }}</span>@enderror</label>
                <label>Operating country <small>Two-letter code</small><input wire:model="country" maxlength="2" autocomplete="country">@error('country')<span>{{ $message }}</span>@enderror</label>
                <label>Timezone<input wire:model="timezone" autocomplete="off">@error('timezone')<span>{{ $message }}</span>@enderror</label>
                <label>Prospective Primary email<input type="email" wire:model="primaryEmail" autocomplete="email">@error('primaryEmail')<span>{{ $message }}</span>@enderror</label>
                <label>Billing interval<select wire:model="billingInterval"><option value="monthly">Monthly</option><option value="annual">Annual</option></select></label>
                <label>Payer intent<select wire:model="payerType"><option value="church">Church pays</option></select><small>Organization payer setup remains outside this bounded form.</small></label>
            </div></section>
            <section><h2>Authorization evidence</h2><div class="central-operation-fields">
                <label>Reason category<select wire:model="reason">@foreach($this->reasonOptions() as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label class="central-field--wide">Required reason note<textarea wire:model="reasonNote" maxlength="500" rows="3"></textarea>@error('reasonNote')<span>{{ $message }}</span>@enderror</label>
                <label>Confirm your password<input type="password" wire:model="password" autocomplete="current-password">@error('password')<span>{{ $message }}</span>@enderror</label>
            </div></section>
            @error('operation')<p class="central-operation-error" role="alert">{{ $message }}</p>@enderror
            <div class="central-operation-submit"><a href="{{ \App\Filament\Central\Pages\Churches::getUrl(panel:'central') }}">Cancel</a><button class="central-button" type="submit">Provision inactive Church</button></div>
        </form>
    </main>
</x-filament-panels::page>
