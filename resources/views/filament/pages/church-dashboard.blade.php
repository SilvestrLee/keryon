@php
    $categoryIcons = ['Content' => 'heroicon-o-document-check', 'Care' => 'heroicon-o-heart', 'Staff' => 'heroicon-o-user-plus', 'Website' => 'heroicon-o-globe-alt', 'Setup' => 'heroicon-o-sparkles'];
    $summaryIcons = ['campaigns' => 'heroicon-o-megaphone', 'congregation' => 'heroicon-o-user-group', 'care' => 'heroicon-o-heart'];
    $shortcutIcons = ['Campaigns' => 'heroicon-o-megaphone', 'Congregation' => 'heroicon-o-user-group', 'Care Center' => 'heroicon-o-heart', 'FaithFlow' => 'heroicon-o-sparkles', 'Design Studio' => 'heroicon-o-swatch', 'Website' => 'heroicon-o-globe-alt'];
@endphp

<x-filament-panels::page>
    <main class="kd-dashboard" data-dashboard-church="{{ $snapshot->churchName }}">
        <header class="kd-context">
            <div class="kd-context__mark" aria-hidden="true"><span></span><span></span></div>
            <div class="kd-context__copy">
                <p class="kd-eyebrow">Church workspace</p>
                <h1>{{ $snapshot->churchName }}</h1>
                <p>See what needs attention, understand what is happening, and continue the work that serves your church.</p>
            </div>
            @if ($snapshot->trial)
                <div class="kd-trial" aria-label="Trial status">
                    <span class="kd-trial__dot" aria-hidden="true"></span>
                    <span><strong>{{ $snapshot->trial['label'] }}</strong><small>{{ $snapshot->trial['days_remaining'] === 1 ? '1 day remaining' : $snapshot->trial['days_remaining'].' days remaining' }}</small></span>
                </div>
            @endif
        </header>

        <section class="kd-section kd-attention" aria-labelledby="action-center-heading">
            <div class="kd-section__heading">
                <div><p class="kd-section__index" aria-hidden="true">01</p><h2 id="action-center-heading">Needs your attention</h2></div>
                <p>Only work you can resolve in this Church appears here.</p>
            </div>
            @if (count($snapshot->actions))
                <div class="kd-action-list">
                    @foreach ($snapshot->actions as $action)
                        <article class="kd-action" data-action-key="{{ $action->key }}">
                            <div class="kd-action__icon" aria-hidden="true"><x-filament::icon :icon="$categoryIcons[$action->category] ?? 'heroicon-o-arrow-right-circle'" /></div>
                            <div class="kd-action__body">
                                <p class="kd-action__category">{{ $action->category }}</p>
                                <h3>@if ($action->count !== null)<span class="kd-action__count">{{ $action->count }}</span>@endif{{ preg_replace('/^'.preg_quote((string) $action->count, '/').'\s+/', '', $action->title) }}</h3>
                                <p>{{ $action->description }}</p>
                            </div>
                            <a href="{{ $action->destination }}" class="kd-action__link" wire:navigate><span>{{ $action->label }}</span><x-filament::icon icon="heroicon-o-arrow-right" aria-hidden="true" /></a>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="kd-caught-up" role="status">
                    <span class="kd-caught-up__icon" aria-hidden="true"><x-filament::icon icon="heroicon-o-check" /></span>
                    <div><h3>You're all caught up</h3><p>Nothing currently needs your attention. Continue working from the areas below.</p></div>
                </div>
            @endif
        </section>

        @if (count($snapshot->summaries))
            <section class="kd-section kd-happening" aria-labelledby="operational-summary-heading">
                    <div class="kd-section__heading kd-section__heading--compact"><div><p class="kd-section__index" aria-hidden="true">02</p><h2 id="operational-summary-heading">What's happening</h2></div></div>
                    <div class="kd-summary-list">
                        @foreach ($snapshot->summaries as $key => $metrics)
                            <article class="kd-summary" data-summary="{{ $key }}">
                                <div class="kd-summary__heading"><span aria-hidden="true"><x-filament::icon :icon="$summaryIcons[$key] ?? 'heroicon-o-circle-stack'" /></span><h3>{{ $key }}</h3></div>
                                <dl>@foreach ($metrics as $metric)<div><dd>{{ $metric->value }}</dd><dt>{{ $metric->label }}</dt></div>@endforeach</dl>
                            </article>
                        @endforeach
                    </div>
            </section>
        @endif

        @if (count($snapshot->charts))
            <section class="kd-section kd-trends" aria-labelledby="operational-trends-heading">
                <div class="kd-section__heading">
                    <div><p class="kd-section__index" aria-hidden="true">03</p><h2 id="operational-trends-heading">Operational trends</h2></div>
                    <p>Recent activity recorded through your Church workspace.</p>
                </div>
                <div class="kd-chart-grid">
                    @foreach ($snapshot->charts as $chart)
                        @include('filament.pages.partials.dashboard-chart', ['chart' => $chart])
                    @endforeach
                </div>
            </section>
        @endif

        @if (count($snapshot->shortcuts))
            <aside class="kd-section kd-work" aria-labelledby="workspace-shortcuts-heading">
                    <div class="kd-section__heading kd-section__heading--compact"><div><p class="kd-section__index" aria-hidden="true">04</p><h2 id="workspace-shortcuts-heading">Continue your work</h2></div></div>
                    <nav class="kd-shortcuts" aria-label="Church workspace shortcuts">
                        @foreach ($snapshot->shortcuts as $shortcut)
                            <a href="{{ $shortcut['destination'] }}" class="kd-shortcut" wire:navigate>
                                <span class="kd-shortcut__icon" aria-hidden="true"><x-filament::icon :icon="$shortcutIcons[$shortcut['label']] ?? 'heroicon-o-squares-2x2'" /></span>
                                <span class="kd-shortcut__copy"><strong>{{ $shortcut['label'] }}</strong><small>{{ $shortcut['description'] }}</small></span>
                                <x-filament::icon icon="heroicon-o-arrow-up-right" class="kd-shortcut__arrow" aria-hidden="true" />
                            </a>
                        @endforeach
                    </nav>
            </aside>
        @endif

        @if (count($snapshot->guidance))
            <section class="kd-section kd-guidance" aria-labelledby="setup-guidance-heading">
                <div class="kd-guidance__heading"><div><h2 id="setup-guidance-heading">Set up when you are ready</h2><p>Optional details that help your church's public presence feel complete.</p></div><span>Optional</span></div>
                <div class="kd-guidance__items">
                    @foreach ($snapshot->guidance as $item)
                        <a href="{{ $item->destination }}" class="kd-guidance__item" wire:navigate>
                            <span><strong>{{ $item->title }}</strong><small>{{ $item->description }}</small></span>
                            <span class="kd-guidance__action">{{ $item->label }}<x-filament::icon icon="heroicon-o-arrow-right" aria-hidden="true" /></span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </main>
</x-filament-panels::page>
