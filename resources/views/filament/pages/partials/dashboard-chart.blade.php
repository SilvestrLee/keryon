@php
    $nonZeroDays = collect($chart->series)->flatMap(fn ($series) => $series->points)->where('value', '>', 0)->pluck('date')->unique()->count();
@endphp

<article class="kd-chart" data-chart-key="{{ $chart->key }}">
    <header class="kd-chart__header">
        <div>
            <p class="kd-chart__range">{{ $chart->rangeLabel }}</p>
            <h3>{{ $chart->title }}</h3>
            <p>{{ $chart->description }}</p>
        </div>
        @if ($chart->hasData())
            <a href="{{ $chart->destination }}" wire:navigate>{{ $chart->actionLabel }}<x-filament::icon icon="heroicon-o-arrow-up-right" aria-hidden="true" /></a>
        @endif
    </header>

    @if ($chart->hasData())
        <div class="kd-chart__summary" aria-label="Chart totals">
            @foreach ($chart->series as $series)
                <span><strong>{{ $series->total() }}</strong>{{ $series->label }}</span>
            @endforeach
        </div>

        <div class="kd-chart__plot">
            <svg viewBox="0 0 100 100" role="img" aria-labelledby="chart-title-{{ $chart->key }} chart-desc-{{ $chart->key }}" preserveAspectRatio="none">
                <title id="chart-title-{{ $chart->key }}">{{ $chart->title }}, {{ $chart->rangeLabel }}</title>
                <desc id="chart-desc-{{ $chart->key }}">{{ collect($chart->series)->map(fn ($series) => $series->label.': '.$series->total())->implode('. ') }}.</desc>
                <g class="kd-chart__grid" aria-hidden="true"><line x1="4" y1="12" x2="96" y2="12"/><line x1="4" y1="50" x2="96" y2="50"/><line x1="4" y1="88" x2="96" y2="88"/></g>
                @foreach ($chart->series as $seriesIndex => $series)
                    @php
                        $plot = $chart->plot($series);
                        $points = collect($plot)->map(fn ($item) => round($item['x'], 2).','.round($item['y'], 2))->implode(' ');
                    @endphp
                    <polyline class="kd-chart__line kd-chart__line--{{ $seriesIndex + 1 }}" points="{{ $points }}" vector-effect="non-scaling-stroke" aria-hidden="true" />
                    @foreach ($plot as $item)
                        @if ($item['point']->value > 0)
                            <circle class="kd-chart__point kd-chart__point--{{ $seriesIndex + 1 }}" cx="{{ $item['x'] }}" cy="{{ $item['y'] }}" r="1.35" vector-effect="non-scaling-stroke" tabindex="0" aria-label="{{ $item['point']->label }}. {{ $series->label }}: {{ $item['point']->value }}">
                                <title>{{ $item['point']->label }} · {{ $series->label }}: {{ $item['point']->value }}</title>
                            </circle>
                        @endif
                    @endforeach
                @endforeach
            </svg>
            <div class="kd-chart__axis" aria-hidden="true">@foreach ($chart->axisLabels() as $label)<span>{{ $label }}</span>@endforeach</div>
        </div>

        <div class="kd-chart__legend" aria-label="Chart series">
            @foreach ($chart->series as $seriesIndex => $series)<span><i class="kd-chart__legend-mark kd-chart__legend-mark--{{ $seriesIndex + 1 }}"></i>{{ $series->label }}</span>@endforeach
        </div>

        @if ($nonZeroDays < 7)
            <p class="kd-chart__early">Your trend will become more useful as Keryon records more activity.</p>
        @endif

        <details class="kd-chart__data">
            <summary>View chart data</summary>
            <div class="kd-chart__data-grid">
                @foreach ($chart->series as $series)
                    <section aria-label="{{ $series->label }} data">
                        <h4>{{ $series->label }}</h4>
                        <dl>@foreach ($series->points as $point)<div><dt>{{ $point->label }}</dt><dd>{{ $point->value }}</dd></div>@endforeach</dl>
                    </section>
                @endforeach
            </div>
        </details>
    @else
        <div class="kd-chart__empty">
            <span aria-hidden="true"><x-filament::icon icon="heroicon-o-chart-bar" /></span>
            <div><h4>{{ $chart->emptyTitle }}</h4><p>{{ $chart->emptyDescription }}</p></div>
            @if ($chart->emptyActionLabel)<a href="{{ $chart->destination }}" wire:navigate>{{ $chart->emptyActionLabel }}<x-filament::icon icon="heroicon-o-arrow-right" aria-hidden="true" /></a>@endif
        </div>
    @endif
</article>
