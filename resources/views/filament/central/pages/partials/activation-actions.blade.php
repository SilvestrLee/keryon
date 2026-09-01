<section class="central-operation" aria-labelledby="activation-operation-title">
    <div><p class="central-eyebrow">Governed operations</p><h2 id="activation-operation-title">Activation actions</h2><p>Actions recheck the current activation state, rotate or revoke credentials canonically, and record immutable platform evidence.</p></div>
    @if(!$operation)<div class="central-operation-buttons">@if($this->canResend())<button class="central-button" wire:click="prepareOperation('resend')">Resend invitation</button>@endif @if($this->canRevoke())<button class="central-button central-button--danger" wire:click="prepareOperation('revoke')">Revoke activation</button>@endif @if(!$this->canResend()&&!$this->canRevoke())<span>No activation operation is available in the current state.</span>@endif</div>@else
        <form wire:submit="runOperation" class="central-operation-confirm"><h3>Confirm {{ $operation === 'resend' ? 'invitation resend' : 'activation revocation' }}</h3><p>{{ $operation === 'resend' ? 'The previous activation token will become invalid and a new delivery will be queued.' : 'This activation becomes terminal and cannot be reopened.' }}</p>
            <label>Reason category<select wire:model="reason">@foreach($this->reasonOptions() as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label>Required reason note<textarea wire:model="reasonNote" maxlength="500" rows="3"></textarea>@error('reasonNote')<span>{{ $message }}</span>@enderror</label>
            <label>Confirm your password<input type="password" wire:model="password" autocomplete="current-password">@error('password')<span>{{ $message }}</span>@enderror</label>
            @error('operation')<p class="central-operation-error" role="alert">{{ $message }}</p>@enderror
            <div><button type="button" class="central-back" wire:click="cancelOperation">Cancel</button><button class="central-button {{ $operation === 'revoke' ? 'central-button--danger' : '' }}" type="submit">Confirm {{ $operation }}</button></div>
        </form>
    @endif
</section>
