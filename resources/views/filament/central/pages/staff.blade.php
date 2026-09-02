<x-filament-panels::page>
    <main class="central">
        <section class="central-section">
            <div class="central-section__head"><h2>Grant access to an existing User</h2><p>Verified identity, one fixed platform role, and no customer membership.</p></div>
            <form wire:submit="add" class="central-form">
                <div class="central-field"><label for="staff-email">Verified email</label><input id="staff-email" type="email" wire:model="email" autocomplete="email">@error('email')<span class="central-error">{{ $message }}</span>@enderror</div>
                <div class="central-field"><label for="staff-role">Platform role</label><select id="staff-role" wire:model="role"><option value="">Choose a role</option>@foreach($this->roleOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>@error('role')<span class="central-error">{{ $message }}</span>@enderror</div>
                <div class="central-field"><label for="staff-password">Confirm your password</label><input id="staff-password" type="password" wire:model="password" autocomplete="current-password">@error('password')<span class="central-error">{{ $message }}</span>@enderror</div>
                <div class="central-field"><label for="staff-reason">Reason</label><select id="staff-reason" wire:model="reason">@foreach(\App\Enums\PlatformAuditReasonCategory::cases() as $category)<option value="{{ $category->value }}">{{ str($category->value)->replace('_', ' ')->headline() }}</option>@endforeach</select></div>
                <div class="central-field central-field--wide"><label for="staff-note">Note</label><textarea id="staff-note" wire:model="note" maxlength="500" rows="2"></textarea>@error('note')<span class="central-error">{{ $message }}</span>@enderror</div>
                <button class="central-button" type="submit">Grant platform access</button>
            </form>
        </section>

        <section class="central-section">
            <div class="central-section__head"><h2>Platform Staff</h2><p>Internal Keryon authority only.</p></div>
            <div class="central-list">
                @foreach($this->memberships() as $membership)
                    <article class="central-row">
                        <div><strong>{{ $membership->user->name }}</strong><small>{{ $membership->user->email }}</small></div>
                        <div><strong>{{ $membership->role->label() }}</strong><small>Activated {{ $membership->activated_at->format('j M Y') }}</small></div>
                        <div class="central-status">{{ $membership->status->value }}@if($membership->suspended_at)<small>{{ $membership->suspended_at->format('j M Y, H:i') }}</small>@elseif($membership->removed_at)<small>{{ $membership->removed_at->format('j M Y, H:i') }}</small>@endif</div>
                        <div class="central-actions">
                            @if($membership->mfaCredential?->isUsable() && $this->platformMembership()->hasCapability(\App\Enums\PlatformCapability::PlatformMfaReset))
                                <button type="button" class="central-action central-action--danger" wire:click="prepareAction({{ $membership->id }}, 'reset-mfa')">Reset MFA</button>
                            @endif
                            @if($membership->status === \App\Enums\PlatformMembershipStatus::ACTIVE && $membership->id !== $this->platformMembership()->id)
                                <button type="button" class="central-action" wire:click="prepareAction({{ $membership->id }}, 'suspend')">Suspend</button>
                                <button type="button" class="central-action central-action--danger" wire:click="prepareAction({{ $membership->id }}, 'remove')">Remove</button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        @if($targetId)
            <section class="central-confirm" aria-labelledby="platform-action-heading">
                <h3 id="platform-action-heading">Confirm {{ $targetAction === 'reset-mfa' ? 'MFA reset' : $targetAction.' access' }}</h3>
                <p>Type the target email, confirm your password, and record why this authority change is required.</p>
                <form wire:submit="confirmAction" class="central-form">
                    <div class="central-field"><label for="target-identifier">Target email</label><input id="target-identifier" wire:model="targetIdentifier">@error('targetIdentifier')<span class="central-error">{{ $message }}</span>@enderror</div>
                    <div class="central-field"><label for="action-password">Confirm your password</label><input id="action-password" type="password" wire:model="password" autocomplete="current-password"></div>
                    <div class="central-field"><label for="action-reason">Reason</label><select id="action-reason" wire:model="reason">@foreach(\App\Enums\PlatformAuditReasonCategory::cases() as $category)<option value="{{ $category->value }}">{{ str($category->value)->replace('_', ' ')->headline() }}</option>@endforeach</select></div>
                    <div class="central-field central-field--wide"><label for="action-note">Required note</label><textarea id="action-note" wire:model="note" maxlength="500" rows="2"></textarea>@error('note')<span class="central-error">{{ $message }}</span>@enderror</div>
                    <button class="central-button" type="submit">Confirm {{ $targetAction }}</button>
                </form>
            </section>
        @endif
    </main>
</x-filament-panels::page>
