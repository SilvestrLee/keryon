<x-filament-panels::page>
    <div class="space-y-8">
        <header class="max-w-3xl">
            <p class="text-sm font-semibold text-amber-700">Church settings</p>
            <h2 class="mt-2 text-2xl font-semibold tracking-tight text-gray-950">Give each person only the access they need</h2>
            <p class="mt-3 leading-7 text-gray-600">Roles compose deliberately. Primary governance, Organization authority, and Care access remain separate.</p>
        </header>

        @if ($deliveryUrl)
            <aside class="rounded-2xl border border-amber-300 bg-amber-50 p-5" aria-live="polite">
                <h3 class="font-semibold text-amber-950">Sensitive local invitation link</h3>
                <p class="mt-1 text-sm leading-6 text-amber-900">Production delivery is not enabled. Copy this link now and share it only through an approved local test channel.</p>
                <div class="mt-3 break-all rounded-xl bg-white p-3 font-mono text-xs text-gray-900">{{ $deliveryUrl }}</div>
                <button type="button" wire:click="$set('deliveryUrl', null)" class="mt-3 text-sm font-semibold text-amber-950 underline">Hide link</button>
            </aside>
        @endif

        @if ($this->canPrepareLocalInvitation())
            <section class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-7" aria-labelledby="invite-heading">
                <h3 id="invite-heading" class="text-lg font-semibold text-gray-950">Invite staff member</h3>
                <form wire:submit="invite" class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto] lg:items-end">
                    <div><label for="invite-email" class="block text-sm font-semibold text-gray-800">Email</label><input id="invite-email" type="email" wire:model="inviteEmail" autocomplete="email" class="mt-2 block w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-gray-950 focus:border-amber-600 focus:ring-amber-600">@error('inviteEmail')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                    <fieldset><legend class="text-sm font-semibold text-gray-800">Roles</legend><div class="mt-2 grid gap-2 sm:grid-cols-3">
                        @foreach ($this->roleOptions() as $value => $label)
                            <label class="rounded-xl border border-gray-200 p-3 text-sm"><span class="flex items-center gap-2"><input type="checkbox" wire:model="inviteRoles" value="{{ $value }}" class="rounded border-gray-400 text-amber-700 focus:ring-amber-600"><strong>{{ $label }}</strong></span>@if ($value === 'care')<span class="mt-2 block text-xs leading-5 text-amber-800">Care access includes private prayer requests and Care Center records.</span>@endif</label>
                        @endforeach
                    </div>@error('inviteRoles')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror</fieldset>
                    <x-filament::button type="submit">Invite staff</x-filament::button>
                </form>
            </section>
        @endif

        <section aria-labelledby="active-staff">
            <h3 id="active-staff" class="text-lg font-semibold text-gray-950">Church staff</h3><p class="mt-1 text-sm text-gray-600">Active, suspended, and removed membership history.</p>
            <div class="mt-4 grid gap-4">
                @forelse ($this->memberships() as $membership)
                    <article class="rounded-2xl border border-gray-200 bg-white p-5"><div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h4 class="font-semibold text-gray-950">{{ $membership->user->name }}</h4>@if ($membership->is_primary)<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">Primary Administrator</span>@endif<span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">{{ $membership->status->label() }}</span></div><p class="mt-1 break-all text-sm text-gray-600">{{ $membership->user->email }}</p><p class="mt-2 text-xs text-gray-500">Joined {{ $membership->joined_at?->format('M j, Y') ?? 'Not recorded' }}</p></div>
                        <div class="w-full xl:max-w-2xl">
                            @if ($this->canManage() && $membership->id !== app(\App\Support\TenantContext::class)->currentMembership()->id && $membership->status->value !== 'removed')
                                <fieldset><legend class="sr-only">Roles for {{ $membership->user->name }}</legend><div class="flex flex-wrap gap-3">@foreach ($this->roleOptions() as $value => $label)<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" wire:model="rolesFor.{{ $membership->id }}" value="{{ $value }}" @disabled($membership->is_primary && $value === 'administrator') class="rounded border-gray-400 text-amber-700 focus:ring-amber-600"><span>{{ $label }}</span></label>@endforeach</div>
                                <div class="mt-4 flex flex-wrap gap-2"><x-filament::button size="sm" color="gray" wire:click="updateRoles({{ $membership->id }})">Save roles</x-filament::button>
                                    @if ($membership->status->value === 'active')<x-filament::button size="sm" color="warning" wire:click="suspend({{ $membership->id }})" wire:confirm="Suspended staff immediately lose access to this Church. Their roles are retained.">Suspend</x-filament::button>@if (app(\App\Support\TenantContext::class)->currentMembership()->is_primary)<x-filament::button size="sm" color="gray" wire:click="transferPrimary({{ $membership->id }})" wire:confirm="Transfer Primary Administrator to {{ $membership->user->name }}? You will remain Church staff, and Care access will not change.">Transfer Primary</x-filament::button>@endif
                                    @else<x-filament::button size="sm" color="success" wire:click="reactivate({{ $membership->id }})">Reactivate</x-filament::button>@endif
                                    <x-filament::button size="sm" color="danger" wire:click="remove({{ $membership->id }})" wire:confirm="Removed staff lose Church access. Membership history is preserved and rejoining requires a new invitation.">Remove</x-filament::button></div></fieldset>
                            @else<div class="flex flex-wrap gap-2">@foreach ($membership->roles as $role)<span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">{{ $role->role->label() }}</span>@endforeach</div>@endif
                        </div>
                    </div></article>
                @empty<div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-gray-600">No Church staff memberships are available.</div>@endforelse
            </div>
        </section>

        <section aria-labelledby="pending-invitations"><h3 id="pending-invitations" class="text-lg font-semibold text-gray-950">Pending invitations</h3><div class="mt-4 grid gap-3">
            @forelse ($this->pendingInvitations() as $invitation)
                <article class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between"><div><h4 class="font-semibold text-gray-950">{{ $invitation->email_normalized }}</h4><div class="mt-2 flex flex-wrap gap-2">@foreach ($invitation->roles as $role)<span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold">{{ $role->role->label() }}</span>@endforeach</div><p class="mt-2 text-xs text-gray-500">Expires {{ $invitation->token_expires_at?->format('M j, Y g:i A') }}</p></div>@if ($this->canManage())<div class="flex gap-2"><x-filament::button size="sm" color="gray" wire:click="resend({{ $invitation->id }})">Resend</x-filament::button><x-filament::button size="sm" color="danger" wire:click="revoke({{ $invitation->id }})" wire:confirm="Revoke this invitation? Its current link will stop working immediately.">Revoke</x-filament::button></div>@endif</article>
            @empty<div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-gray-600">No pending invitations.</div>@endforelse
        </div></section>
    </div>
</x-filament-panels::page>
