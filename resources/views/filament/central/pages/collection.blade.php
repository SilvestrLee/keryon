<x-filament-panels::page>
    <main class="central central-read">
        <header class="central-page-head"><div><p class="central-eyebrow">Platform read plane</p><h1>{{ $this->getTitle() }}</h1><p>Allowlisted operational records. This surface is read-only.</p></div></header>
        <section class="central-section" aria-label="{{ $this->getTitle() }} filters">
            <div class="central-filters">
                @if(property_exists($this, 'search'))<label>Search<input type="search" wire:model.live.debounce.350ms="search" placeholder="Name, identifier, or exact email"></label>@endif
                @foreach(['state','status','country','activation','delivery','market','tls','primary','type','failure'] as $filter)
                    @if(property_exists($this, $filter))<label>{{ str($filter)->headline() }}<input wire:model.live.debounce.350ms="{{ $filter }}" placeholder="All"></label>@endif
                @endforeach
            </div>
            @php($records = $this->records())
            @if($records->isEmpty())<div class="central-empty">No matching operational records.</div>@else
                <div class="central-table-wrap"><table class="central-table"><thead><tr>@foreach($this->columns() as $column)<th scope="col">{{ $column }}</th>@endforeach</tr></thead><tbody>
                @foreach($records as $record)<tr tabindex="0" onclick="window.location='{{ $this->detailUrl($record) }}'" onkeydown="if(event.key==='Enter'){window.location='{{ $this->detailUrl($record) }}'}">@foreach($this->cells($record) as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach
                </tbody></table></div><div class="central-pagination">{{ $records->links() }}</div>
            @endif
        </section>
    </main>
</x-filament-panels::page>
