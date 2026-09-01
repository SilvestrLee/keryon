<section class="central-operation" aria-labelledby="domain-operation-title">
    <div><p class="central-eyebrow">Governed operation</p><h2 id="domain-operation-title">Domain verification</h2><p>Retry the canonical ownership, routing, and TLS verification sequence. No status can be forced.</p></div>
    @if(!$operation)@if($this->canRetry())<button class="central-button" wire:click="prepareOperation('retry')">Retry verification</button>@else<span>No retry is available for this domain.</span>@endif @else
        <form wire:submit="runOperation" class="central-operation-confirm"><h3>Confirm verification retry</h3><p>The request will fail truthfully when DNS or TLS infrastructure is unavailable.</p>
            <label>Reason category<select wire:model="reason">@foreach($this->reasonOptions() as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label>Required reason note<textarea wire:model="reasonNote" maxlength="500" rows="3"></textarea>@error('reasonNote')<span>{{ $message }}</span>@enderror</label>
            <label>Confirm your password<input type="password" wire:model="password" autocomplete="current-password">@error('password')<span>{{ $message }}</span>@enderror</label>
            @error('operation')<p class="central-operation-error" role="alert">{{ $message }}</p>@enderror
            <div><button type="button" class="central-back" wire:click="cancelOperation">Cancel</button><button class="central-button" type="submit">Queue verification retry</button></div>
        </form>
    @endif
</section>
