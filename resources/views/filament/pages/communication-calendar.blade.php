<x-filament-panels::page>
    <main class="kc-calendar" data-calendar-church="{{ $churchName }}">
        <header class="kc-calendar-hero">
            <div><p class="kc-kicker">Content Calendar</p><h1>Plan the week clearly</h1><p>Target dates show communication intent. They do not schedule or publish work.</p></div>
            @if ($canManageCampaigns)<a href="{{ \App\Filament\Pages\Campaigns::getUrl() }}" class="kc-primary-link" wire:navigate>Manage Campaigns<x-filament::icon icon="heroicon-o-arrow-right" aria-hidden="true" /></a>@endif
        </header>

        <nav class="kc-local-nav" aria-label="Communications workspace">
            <a href="{{ \App\Filament\Pages\CommunicationsHub::getUrl() }}" wire:navigate>Overview</a>
            <a href="{{ \App\Filament\Pages\CommunicationCalendar::getUrl() }}" aria-current="page" wire:navigate>Calendar</a>
            <a href="{{ \App\Filament\Pages\Campaigns::getUrl() }}" wire:navigate>Campaigns</a>
            <a href="{{ \App\Filament\Resources\ContentItemResource::getUrl('index') }}" wire:navigate>Content</a>
        </nav>

        <section class="kc-calendar-tools" aria-label="Calendar filters">
            <div class="kc-period-switch" role="group" aria-label="Calendar period">
                <button type="button" wire:click="$set('period', 'today')" @class(['is-active' => $period === 'today'])>Today</button>
                <button type="button" wire:click="$set('period', 'week')" @class(['is-active' => $period === 'week'])>This Week</button>
            </div>
            <div class="kc-filters">
                <label><span>Campaign</span><select wire:model.live="campaign"><option value="">All Campaigns</option>@foreach ($campaigns as $id => $title)<option value="{{ $id }}">{{ $title }}</option>@endforeach</select></label>
                <label><span>Channel</span><select wire:model.live="channel"><option value="">All channels</option>@foreach ($channels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label><span>Preparation</span><select wire:model.live="preparation"><option value="">All states</option>@foreach ($preparations as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            </div>
        </section>

        <section class="kc-agenda" aria-labelledby="calendar-period-heading">
            @php
                $visibleEntries = collect($groups)->flatten(1);
                $attentionCount = $visibleEntries->whereIn('outcomeKey', ['due', 'overdue'])->count();
                $readyCount = $visibleEntries->where('outcomeKey', 'ready')->count();
                $publishedCount = $visibleEntries->where('outcomeKey', 'published')->count();
            @endphp
            <div class="kc-agenda-heading"><div><h2 id="calendar-period-heading">{{ $period === 'today' ? 'Today' : 'This week' }}</h2><p>{{ $periodLabel }} · {{ $timezone }}</p></div><span>{{ $visibleEntries->count() }} planned</span></div>
            @if ($visibleEntries->isNotEmpty())
                <div class="kc-outcome-summary" aria-label="Calendar outcome summary">
                    <span><strong>{{ $attentionCount }}</strong> need attention now</span>
                    <span><strong>{{ $readyCount }}</strong> ready, not executed</span>
                    <span><strong>{{ $publishedCount }}</strong> published</span>
                </div>
            @endif

            @forelse ($groups as $date => $entries)
                @php $day = \Carbon\CarbonImmutable::parse($date, $timezone); @endphp
                <section class="kc-day" aria-labelledby="day-{{ $date }}">
                    <header><time datetime="{{ $date }}"><strong id="day-{{ $date }}">{{ $day->isToday() ? 'Today' : $day->format('l') }}</strong><span>{{ $day->format('j F') }}</span></time><span>{{ count($entries) }} {{ count($entries) === 1 ? 'communication' : 'communications' }}</span></header>
                    <div class="kc-day-entries">
                        @foreach ($entries as $entry)
                            <article class="kc-calendar-entry" data-preparation="{{ $entry->preparationKey }}">
                                <time datetime="{{ $entry->targetAt->toIso8601String() }}">{{ $entry->targetAt->format('H:i') }}</time>
                                <div class="kc-entry-main"><p class="kc-entry-channel">{{ $entry->channel->label() }}</p><h3>{{ $entry->title }}</h3><span>{{ $entry->campaignTitle }}@if ($entry->contentStatus) · {{ $entry->contentStatus }}@endif</span>@if ($entry->outcomeKey === 'overdue')<p class="kc-outcome-note">Target time passed. Outcome not recorded. No attributable execution was recorded.</p>@elseif ($entry->outcomeKey === 'published')<p class="kc-outcome-note">Published {{ $entry->executedAt?->setTimezone($timezone)->format('j M, H:i') }} from this Website draft lineage.</p>@elseif ($entry->outcomeKey === 'superseded')<p class="kc-outcome-note">A later Website handoff replaced this working lineage before publication.</p>@endif</div>
                                <div class="kc-entry-states"><span class="kc-state kc-state--{{ str_replace('_', '-', $entry->preparationKey) }}">{{ $entry->preparationLabel }}</span><span class="kc-state kc-state--outcome-{{ $entry->outcomeKey }}">{{ $entry->outcomeLabel }}</span></div>
                                <div class="kc-entry-actions">
                                    @foreach ($entry->actions as $action)
                                        <a href="{{ $action->destination }}" wire:navigate>{{ $action->label }}<x-filament::icon icon="heroicon-o-arrow-right" aria-hidden="true" /></a>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="kc-calendar-empty" role="status"><span aria-hidden="true"><x-filament::icon icon="heroicon-o-calendar-days" /></span><div><h3>Nothing is planned {{ $period === 'today' ? 'today' : 'for this week' }}</h3><p>Campaign communications appear here when they receive a target date.</p></div>@if ($canManageCampaigns)<a href="{{ \App\Filament\Pages\Campaigns::getUrl() }}" wire:navigate>Manage Campaigns</a>@endif</div>
            @endforelse
        </section>

        @if (count($unplaced))
            <section class="kc-section kc-unplaced" aria-labelledby="calendar-unplaced-heading">
                <div class="kc-section-heading"><div><h2 id="calendar-unplaced-heading">Not yet placed on the Calendar</h2><p>These planned communications do not have a target date.</p></div><span>{{ count($unplaced) }}</span></div>
                <div class="kc-unplaced-list">@foreach ($unplaced as $entry)<a href="{{ $entry->actions[0]->destination }}" wire:navigate><span><strong>{{ $entry->title }}</strong><small>{{ $entry->campaignTitle }} · {{ $entry->channel->label() }}</small></span><span>{{ $entry->preparationLabel }}<x-filament::icon icon="heroicon-o-arrow-right" aria-hidden="true" /></span></a>@endforeach</div>
            </section>
        @endif
    </main>
</x-filament-panels::page>
