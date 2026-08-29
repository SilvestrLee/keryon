<div class="org-context" aria-label="Current Organization workspace">
    <div>
        <div class="org-context__label">Organization workspace</div>
        <div class="org-context__name">{{ $this->organization()->name }}</div>
    </div>
    @if ($this->canSwitchOrganization())
        <a class="org-context__switch" href="{{ \App\Filament\Organization\Pages\SelectOrganization::getUrl(panel: 'organization') }}" wire:navigate>
            Switch organization
        </a>
    @endif
</div>
