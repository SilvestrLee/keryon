<x-filament-panels::page>
    @include('filament.organization.partials.context')

    <header class="org-page-heading">
        <p class="org-eyebrow">People &amp; access</p>
        <h1>Organization responsibilities</h1>
        <p>Organization authority is separate from Church staff access and remains bound to explicit hierarchy scopes.</p>
    </header>

    <section class="org-section">
        <div class="org-section__header">
            <div>
                <h2>Organization members</h2>
                <p>Manage memberships and responsibility assignments without manufacturing Church access.</p>
            </div>
        </div>
        <div class="org-list">
            @forelse ($this->memberships() as $membership)
                <div class="org-person">
                    <div class="org-person__header">
                        <div>
                            <div class="org-row__title">{{ $membership->user->name }}</div>
                            <div class="org-row__meta">{{ $membership->user->email }} · {{ str($membership->status->value)->headline() }}</div>
                        </div>
                        <div class="org-actions">
                            @if ($membership->status->value === 'invited' || $membership->status->value === 'suspended')
                                <x-filament::button size="xs" wire:click="mountAction('activateMembership', { membership: {{ $membership->id }} })">Activate</x-filament::button>
                            @endif
                            @if ($this->isActive($membership))
                                <x-filament::button size="xs" color="gray" wire:click="mountAction('assignRole', { membership: {{ $membership->id }} })">Assign role</x-filament::button>
                                <x-filament::button size="xs" color="warning" wire:click="mountAction('suspendMembership', { membership: {{ $membership->id }} })">Suspend</x-filament::button>
                                <x-filament::button size="xs" color="danger" wire:click="mountAction('removeMembership', { membership: {{ $membership->id }} })">Remove</x-filament::button>
                            @endif
                        </div>
                    </div>

                    <div class="org-roles">
                        @forelse ($membership->roleAssignments as $assignment)
                            <div class="org-role">
                                <div>
                                    <strong>{{ $assignment->role->label() }}</strong>
                                    <span>{{ $assignment->unit->name }}</span>
                                    @if (! $this->roleIsActive($assignment))
                                        <span class="org-badge">{{ str($assignment->status->value)->headline() }}</span>
                                    @endif
                                </div>
                                @if ($this->roleIsActive($assignment))
                                    <div class="org-actions">
                                        @if ($assignment->role !== \App\Enums\OrganizationRole::ORGANIZATION_ADMINISTRATOR)
                                            <x-filament::button size="xs" color="gray" wire:click="mountAction('changeRoleScope', { assignment: {{ $assignment->id }} })">Change scope</x-filament::button>
                                        @endif
                                        <x-filament::button size="xs" color="danger" wire:click="mountAction('removeRole', { assignment: {{ $assignment->id }} })">Remove role</x-filament::button>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="org-row__meta">No active authority has been assigned yet.</p>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="org-empty">
                    <h3>No additional Organization staff</h3>
                    <p>Invite trusted Organization administrators when you are ready.</p>
                </div>
            @endforelse
        </div>
    </section>

    <x-filament-actions::modals />
</x-filament-panels::page>
