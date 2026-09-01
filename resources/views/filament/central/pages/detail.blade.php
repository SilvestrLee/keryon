<x-filament-panels::page>
    @php($item = $this->item())
    <main class="central central-read">
        <header class="central-page-head"><div><p class="central-eyebrow">Read-only operational detail</p><h1>{{ $this->getTitle() }}</h1><p>Only fields approved for the platform plane are loaded.</p></div><button class="central-back" type="button" onclick="history.back()">Back to list</button></header>
        <div class="central-detail-grid">@foreach($this->sections($item) as $heading => $fields)<section class="central-section"><div class="central-section__head"><h2>{{ $heading }}</h2></div><dl class="central-definition">@foreach($fields as $label=>$value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl></section>@endforeach</div>
    </main>
</x-filament-panels::page>
