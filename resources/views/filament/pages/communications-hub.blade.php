@php
    $destinationIcons = ['Campaigns' => 'heroicon-o-megaphone', 'Calendar' => 'heroicon-o-calendar-days', 'Content' => 'heroicon-o-document-text', 'Designs' => 'heroicon-o-swatch', 'FaithFlow' => 'heroicon-o-sparkles', 'Website' => 'heroicon-o-globe-alt'];
    $actionIcons = ['Content' => 'heroicon-o-document-check', 'Campaigns' => 'heroicon-o-clock'];
@endphp

<x-filament-panels::page>
    <main class="kc-hub" data-communications-church="{{ $snapshot->churchName }}">
        <header class="kc-hero">
            <div>
                <p class="kc-kicker">Church communications</p>
                <h1>{{ $snapshot->churchName }}</h1>
                <p>Plan the message, prepare the work, and keep every communication moving in one Church workspace.</p>
            </div>
            <a href="{{ \App\Filament\Pages\CommunicationCalendar::getUrl() }}" class="kc-primary-link" wire:navigate>
                Open Calendar
                <x-filament::icon icon="heroicon-o-arrow-right" aria-hidden="true" />
            </a>
        </header>

        <nav class="kc-local-nav" aria-label="Communications workspace">
            <a href="{{ \App\Filament\Pages\CommunicationsHub::getUrl() }}" aria-current="page" wire:navigate>Overview</a>
            <a href="{{ \App\Filament\Pages\CommunicationCalendar::getUrl() }}" wire:navigate>Calendar</a>
            @foreach ($snapshot->destinations as $destination)
                @if (! in_array($destination['label'], ['Calendar'], true))
                    <a href="{{ $destination['destination'] }}" wire:navigate>{{ $destination['label'] }}</a>
                @endif
            @endforeach
        </nav>

        <section class="kc-section kc-attention" aria-labelledby="communications-attention-heading">
            <div class="kc-section-heading">
                <div><h2 id="communications-attention-heading">Needs attention</h2><p>Work you can move forward in Church communications.</p></div>
                @if (count($snapshot->actions))<span>{{ count($snapshot->actions) }} {{ count($snapshot->actions) === 1 ? 'item' : 'items' }}</span>@endif
            </div>

            @if (count($snapshot->actions))
                <div class="kc-attention-list">
                    @foreach ($snapshot->actions as $action)
                        <article class="kc-attention-item" data-action-key="{{ $action->key }}">
                            <span class="kc-attention-icon" aria-hidden="true"><x-filament::icon :icon="$actionIcons[$action->category] ?? 'heroicon-o-arrow-right-circle'" /></span>
                            <div><p>{{ $action->category }}</p><h3>{{ $action->title }}</h3><span>{{ $action->description }}</span></div>
                            <a href="{{ $action->destination }}" wire:navigate>{{ $action->label }}<x-filament::icon icon="heroicon-o-arrow-right" aria-hidden="true" /></a>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="kc-clear-state" role="status">
                    <span aria-hidden="true"><x-filament::icon icon="heroicon-o-check" /></span>
                    <div><h3>Communications are clear</h3><p>No current communications work needs your attention.</p></div>
                </div>
            @endif
        </section>

        @if (count($snapshot->today) || $snapshot->weekCounts['total'] > 0)
            <section class="kc-section kc-planning" aria-labelledby="communications-week-heading">
                <div class="kc-section-heading">
                    <div><h2 id="communications-week-heading">This week</h2><p>Planned communications in {{ $snapshot->timezone }}.</p></div>
                    <a href="{{ \App\Filament\Pages\CommunicationCalendar::getUrl() }}" wire:navigate>View full Calendar<x-filament::icon icon="heroicon-o-arrow-right" aria-hidden="true" /></a>
                </div>
                <div class="kc-week-layout">
                    <div class="kc-week-summary">
                        <p><strong>{{ $snapshot->weekCounts['total'] }}</strong> {{ $snapshot->weekCounts['total'] === 1 ? 'communication planned' : 'communications planned' }}</p>
                        <dl>
                            <div><dt>Prepared</dt><dd>{{ $snapshot->weekCounts['prepared'] }}</dd></div>
                            <div><dt>Awaiting approval</dt><dd>{{ $snapshot->weekCounts['awaiting_approval'] }}</dd></div>
                            <div><dt>In preparation</dt><dd>{{ $snapshot->weekCounts['in_preparation'] }}</dd></div>
                            <div><dt>Not started</dt><dd>{{ $snapshot->weekCounts['not_started'] }}</dd></div>
                        </dl>
                    </div>
                    <div class="kc-today">
                        <div class="kc-today-heading"><h3>Today</h3><span>{{ now($snapshot->timezone)->format('D, j M') }}</span></div>
                        @forelse ($snapshot->today as $entry)
                            <article class="kc-compact-entry">
                                <time datetime="{{ $entry->targetAt->toIso8601String() }}">{{ $entry->targetAt->format('H:i') }}</time>
                                <div><p>{{ $entry->title }}</p><span>{{ $entry->campaignTitle }} · {{ $entry->channel->label() }}</span></div>
                                <span class="kc-state kc-state--{{ str_replace('_', '-', $entry->preparationKey) }}">{{ $entry->preparationLabel }}</span>
                            </article>
                        @empty
                            <div class="kc-today-empty"><p>Nothing is planned for today.</p><span>This week's work remains visible in the Calendar.</span></div>
                        @endforelse
                    </div>
                </div>
            </section>
        @elseif (count($snapshot->campaigns) === 0)
            <section class="kc-section kc-start" aria-labelledby="communications-start-heading">
                <span aria-hidden="true"><x-filament::icon icon="heroicon-o-megaphone" /></span>
                <div><h2 id="communications-start-heading">Your communications workspace is ready</h2><p>Create a Campaign for a coordinated message, or begin directly in Content Studio.</p></div>
                @if ($snapshot->canManageCampaigns)<a href="{{ \App\Filament\Pages\Campaigns::getUrl() }}" wire:navigate>Create Campaign</a>@endif
            </section>
        @endif

        @if (count($snapshot->campaigns))
            <section class="kc-section kc-campaigns" aria-labelledby="active-campaigns-heading">
                <div class="kc-section-heading"><div><h2 id="active-campaigns-heading">Active planning</h2><p>Current Campaigns and their factual preparation state.</p></div></div>
                <div class="kc-campaign-grid">
                    @foreach ($snapshot->campaigns as $campaign)
                        <article class="kc-campaign-card">
                            <div class="kc-campaign-meta"><span>{{ $campaign->status }}</span><time>{{ $campaign->period }}</time></div>
                            <h3>{{ $campaign->title }}</h3>
                            <p>{{ $campaign->plannedCount }} {{ $campaign->plannedCount === 1 ? 'planned communication' : 'planned communications' }}</p>
                            <dl>
                                <div><dt>Prepared</dt><dd>{{ $campaign->preparation['prepared'] ?? 0 }}</dd></div>
                                <div><dt>Awaiting approval</dt><dd>{{ $campaign->preparation['awaiting_approval'] ?? 0 }}</dd></div>
                                <div><dt>Still in preparation</dt><dd>{{ ($campaign->preparation['in_preparation'] ?? 0) + ($campaign->preparation['not_started'] ?? 0) + ($campaign->preparation['outstanding'] ?? 0) }}</dd></div>
                            </dl>
                            <a href="{{ $campaign->destination }}" wire:navigate>Continue Campaign<x-filament::icon icon="heroicon-o-arrow-right" aria-hidden="true" /></a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if (count($snapshot->unplaced))
            <section class="kc-section kc-unplaced" aria-labelledby="unplaced-heading">
                <div class="kc-section-heading"><div><h2 id="unplaced-heading">Not yet placed</h2><p>Campaign communications without a target date.</p></div><span>{{ count($snapshot->unplaced) }}</span></div>
                <div class="kc-unplaced-list">
                    @foreach ($snapshot->unplaced as $entry)
                        <a href="{{ $entry->actions[0]->destination }}" wire:navigate><span><strong>{{ $entry->title }}</strong><small>{{ $entry->campaignTitle }} · {{ $entry->channel->label() }}</small></span><span>{{ $entry->preparationLabel }}<x-filament::icon icon="heroicon-o-arrow-right" aria-hidden="true" /></span></a>
                    @endforeach
                </div>
            </section>
        @endif

        @if (count($snapshot->destinations))
            <aside class="kc-section kc-continue" aria-labelledby="communications-continue-heading">
                <div class="kc-section-heading"><div><h2 id="communications-continue-heading">Continue your work</h2><p>Open the canonical workspace for each part of the communication.</p></div></div>
                <nav aria-label="Communications destinations">
                    @foreach ($snapshot->destinations as $destination)
                        <a href="{{ $destination['destination'] }}" wire:navigate>
                            <span aria-hidden="true"><x-filament::icon :icon="$destinationIcons[$destination['label']] ?? 'heroicon-o-arrow-up-right'" /></span>
                            <span><strong>{{ $destination['label'] }}</strong><small>{{ $destination['description'] }}</small></span>
                            <x-filament::icon icon="heroicon-o-arrow-up-right" aria-hidden="true" />
                        </a>
                    @endforeach
                </nav>
            </aside>
        @endif
    </main>
</x-filament-panels::page>
