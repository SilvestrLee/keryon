@php($organizationScopes = $this->organizationScopes())
<aside class="org-context" aria-label="Current Organization workspace and authorized scope">
    <div class="org-context__identity">
        <div class="org-context__label">Organization workspace</div>
        <div class="org-context__name">{{ $this->organization()->name }}</div>
        @if (count($organizationScopes) === 1)
            <div class="org-context__scope"><span>Viewing</span> {{ $organizationScopes[0]['path'] }}</div>
        @elseif (count($organizationScopes) > 1)
            <div class="org-context__scope"><span>Viewing</span> {{ count($organizationScopes) }} assigned scopes</div>
        @endif
    </div>
    @if ($this->canSwitchOrganization())
        <a class="org-context__switch" href="{{ \App\Filament\Organization\Pages\SelectOrganization::getUrl(panel: 'organization') }}" wire:navigate>
            Switch organization
        </a>
    @endif
</aside>
