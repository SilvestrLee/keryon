<x-filament-panels::page>
    <main class="central central-read"><header class="central-page-head"><div><p class="central-eyebrow">Trust &amp; Infrastructure</p><h1>Provider Status</h1><p>Configuration and governance evidence only. Runtime health is unknown where no heartbeat exists.</p></div></header>
        <div class="central-provider-grid">@foreach($this->statuses() as $status)<article class="central-provider"><div><span>{{ $status->category }}</span><h2>{{ $status->name }}</h2></div><strong>{{ $status->operationalState }}</strong><dl><div><dt>Environment</dt><dd>{{ $status->environment }}</dd></div><div><dt>Governance</dt><dd>{{ $status->governanceState }}</dd></div><div><dt>Configured</dt><dd>{{ $status->configured ? 'Yes' : 'No' }}</dd></div></dl>@if($status->blockers)<ul>@foreach($status->blockers as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>@endif</article>@endforeach</div>
    </main>
</x-filament-panels::page>
