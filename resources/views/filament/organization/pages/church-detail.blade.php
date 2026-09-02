<x-filament-panels::page>
    @php($church = $this->church())
    @include('filament.organization.partials.context')

    <nav class="org-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ \App\Filament\Organization\Pages\OrganizationChurches::getUrl(panel: 'organization') }}" wire:navigate>Churches</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ $church->name }}</span>
    </nav>

    <header class="org-page-heading org-page-heading--detail">
        <p class="org-eyebrow">Church operational detail</p>
        <h1>{{ $church->name }}</h1>
        <p>{{ $church->unitPath }}</p>
    </header>

    @if ($church->needsAttention())
        <section class="org-attention org-attention--detail" aria-labelledby="church-attention-heading">
            <div class="org-section__header">
                <div>
                    <p class="org-eyebrow">Attention</p>
                    <h2 id="church-attention-heading">{{ count($church->attention) }} operational {{ str('condition')->plural(count($church->attention)) }}</h2>
                </div>
            </div>
            <ul class="org-condition-list">
                @foreach ($church->attention as $condition)
                    <li><span aria-hidden="true">!</span>{{ $condition }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="org-section" aria-labelledby="church-state-heading">
        <div class="org-section__header">
            <div>
                <p class="org-eyebrow">Organization-visible metadata</p>
                <h2 id="church-state-heading">Operational state</h2>
                <p>This view does not grant Church workspace access or reveal congregation, Care, content or media records.</p>
            </div>
        </div>
        <dl class="org-detail-grid">
            <div><dt>Church state</dt><dd>{{ $church->churchState }}</dd></div>
            <div><dt>Onboarding</dt><dd>{{ $church->onboardingState }}</dd></div>
            <div><dt>Website</dt><dd>{{ $church->websiteState }}</dd></div>
            <div><dt>Domain</dt><dd>{{ $church->domainState }}</dd></div>
            <div class="org-detail-grid__wide"><dt>Hierarchy location</dt><dd>{{ $church->unitPath }}</dd></div>
        </dl>
    </section>

    <aside class="org-boundary-note">
        <strong>Church authority remains independent</strong>
        <p>To operate this Church, use its Church workspace only when your account has a separate active Church membership.</p>
    </aside>
</x-filament-panels::page>
