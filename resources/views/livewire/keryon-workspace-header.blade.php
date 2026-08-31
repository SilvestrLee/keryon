<div
    class="ks-shell"
    x-data="{ workspaceOpen: false, languageOpen: false, mobileMenuOpen: false, activeResult: 0 }"
    x-on:keydown.window.meta.k.prevent="$wire.openSearch()"
    x-on:keydown.window.ctrl.k.prevent="$wire.openSearch()"
    x-on:keydown.escape.window="workspaceOpen = false; languageOpen = false; mobileMenuOpen = false; $wire.closeSearch()"
>
    <div class="ks-workspace">
        <button type="button" class="ks-control ks-workspace__trigger" x-on:click="workspaceOpen = ! workspaceOpen" x-bind:aria-expanded="workspaceOpen" aria-haspopup="menu" aria-label="{{ __('shell.switch_workspace') }}">
            <span class="ks-workspace__mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($workspace?->name ?? 'K', 0, 1)) }}</span>
            <span class="ks-workspace__identity"><strong>{{ $workspace?->name ?? __('shell.workspace') }}</strong><small>{{ $workspaceType->label() }}</small></span>
            <x-filament::icon icon="heroicon-m-chevron-down" aria-hidden="true" />
        </button>

        <div class="ks-menu ks-workspace__menu" x-cloak x-show="workspaceOpen" x-transition.opacity x-on:click.outside="workspaceOpen = false" role="menu" aria-label="{{ __('shell.switch_workspace') }}">
            @foreach ([\App\Enums\WorkspaceType::Church, \App\Enums\WorkspaceType::Organization] as $type)
                @if (($workspaces[$type->value] ?? collect())->isNotEmpty())
                    <p class="ks-menu__heading">{{ $type->label() }}</p>
                    @foreach ($workspaces[$type->value] as $option)
                        <form method="POST" action="{{ route('workspace.switch', ['type' => $option->type->value, 'workspace' => $option->id]) }}">
                            @csrf
                            <button type="submit" role="menuitem" @class(['ks-menu__item', 'is-current' => $option->current])>
                                <span><strong>{{ $option->name }}</strong><small>{{ $option->type->label() }}</small></span>
                                @if ($option->current)<span class="ks-current">{{ __('shell.current') }}</span>@endif
                            </button>
                        </form>
                    @endforeach
                @endif
            @endforeach
        </div>
    </div>

    <button type="button" class="ks-search-trigger" wire:click="openSearch" aria-haspopup="dialog">
        <x-filament::icon icon="heroicon-o-magnifying-glass" aria-hidden="true" />
        <span>{{ __('shell.search_placeholder') }}</span>
        <kbd>⌘K</kbd>
    </button>

    @if ($websiteUrl)
        <a class="ks-control ks-website" href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer">
            <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" aria-hidden="true" />
            <span>{{ __('shell.go_to_website') }}</span>
        </a>
    @endif

    <div class="ks-language">
        <button type="button" class="ks-control ks-language__trigger" x-on:click="languageOpen = ! languageOpen" x-bind:aria-expanded="languageOpen" aria-haspopup="menu" aria-label="{{ __('shell.language') }}">
            <x-filament::icon icon="heroicon-o-language" aria-hidden="true" />
            <span>{{ $locales[$currentLocale]['self_label'] ?? strtoupper($currentLocale) }}</span>
        </button>
        <div class="ks-menu ks-language__menu" x-cloak x-show="languageOpen" x-transition.opacity x-on:click.outside="languageOpen = false" role="menu">
            <p class="ks-menu__heading">{{ __('shell.language') }}</p>
            @foreach ($locales as $locale => $language)
                <form method="POST" action="{{ route('account.locale') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $locale }}">
                    <button class="ks-menu__item" type="submit" role="menuitemradio" aria-checked="{{ $currentLocale === $locale ? 'true' : 'false' }}">
                        <span><strong>{{ $language['self_label'] }}</strong><small>{{ $language['label'] }}</small></span>
                        @if ($currentLocale === $locale)<span class="ks-current">{{ __('shell.current') }}</span>@endif
                    </button>
                </form>
            @endforeach
        </div>
    </div>

    <div class="ks-mobile-actions">
        <button type="button" class="ks-control" x-on:click="mobileMenuOpen = ! mobileMenuOpen" x-bind:aria-expanded="mobileMenuOpen" aria-haspopup="menu" aria-label="{{ __('shell.more_actions') }}">
            <x-filament::icon icon="heroicon-o-ellipsis-horizontal" aria-hidden="true" />
        </button>
        <div class="ks-menu" x-cloak x-show="mobileMenuOpen" x-transition.opacity x-on:click.outside="mobileMenuOpen = false" role="menu">
            @if ($websiteUrl)
                <a class="ks-menu__item" href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer" role="menuitem">
                    <span><strong>{{ __('shell.go_to_website') }}</strong><small>{{ __('shell.public_church_website') }}</small></span>
                    <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" aria-hidden="true" />
                </a>
            @endif
            <p class="ks-menu__heading">{{ __('shell.language') }}</p>
            @foreach ($locales as $locale => $language)
                <form method="POST" action="{{ route('account.locale') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $locale }}">
                    <button class="ks-menu__item" type="submit" role="menuitemradio" aria-checked="{{ $currentLocale === $locale ? 'true' : 'false' }}">
                        <span><strong>{{ $language['self_label'] }}</strong><small>{{ $language['label'] }}</small></span>
                        @if ($currentLocale === $locale)<span class="ks-current">{{ __('shell.current') }}</span>@endif
                    </button>
                </form>
            @endforeach
        </div>
    </div>

    @if ($searchOpen)
        <div class="ks-search" role="dialog" aria-modal="true" aria-labelledby="ks-search-title" x-on:click.self="$wire.closeSearch()">
            <div class="ks-search__panel" x-trap.inert.noscroll="true">
                <header class="ks-search__header">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" aria-hidden="true" />
                    <label class="sr-only" id="ks-search-title" for="ks-global-search">{{ __('shell.search') }}</label>
                    <input id="ks-global-search" type="search" wire:model.live.debounce.250ms="query" placeholder="{{ __('shell.search_placeholder') }}" autocomplete="off" autofocus
                        x-on:keydown.arrow-down.prevent="activeResult = Math.min(activeResult + 1, $refs.results?.querySelectorAll('a').length - 1); $refs.results?.querySelectorAll('a')[activeResult]?.focus()">
                    <button type="button" wire:click="closeSearch" aria-label="{{ __('shell.close') }}"><x-filament::icon icon="heroicon-o-x-mark" /></button>
                </header>
                <div class="ks-search__body" x-ref="results" aria-live="polite">
                    @if (mb_strlen(trim($query)) < $minimumLength)
                        <div class="ks-search__state"><strong>{{ __('shell.search_hint') }}</strong><p>{{ __('shell.search_short_query', ['count' => $minimumLength]) }}</p></div>
                    @elseif ($resultCount === 0)
                        <div class="ks-search__state"><strong>{{ __('shell.search_no_results', ['query' => $query]) }}</strong></div>
                    @else
                        <p class="sr-only">{{ trans_choice('shell.search_results', $resultCount, ['count' => $resultCount]) }}</p>
                        @foreach ($groups as $group => $results)
                            <section class="ks-search__group" aria-labelledby="ks-group-{{ \Illuminate\Support\Str::slug($group) }}">
                                <h3 id="ks-group-{{ \Illuminate\Support\Str::slug($group) }}">{{ $group }}</h3>
                                @foreach ($results as $result)
                                    <a href="{{ $result->url }}" wire:navigate>
                                        <span class="ks-result__icon" aria-hidden="true"><x-filament::icon icon="heroicon-o-arrow-right" /></span>
                                        <span><small>{{ $result->type }}</small><strong>{{ $result->title }}</strong>@if($result->description)<em>{{ $result->description }}</em>@endif</span>
                                    </a>
                                @endforeach
                            </section>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
