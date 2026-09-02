<div
    class="ks-shell"
    x-data="{ workspaceOpen: false, languageOpen: false, mobileMenuOpen: false, accountOpen: false, activeResult: 0 }"
    x-on:keydown.window.meta.k.prevent="$wire.openSearch()"
    x-on:keydown.window.ctrl.k.prevent="$wire.openSearch()"
    x-on:keydown.escape.window="workspaceOpen = false; languageOpen = false; mobileMenuOpen = false; if (accountOpen) { accountOpen = false; $nextTick(() => $refs.accountTrigger.focus()) }; $wire.closeSearch()"
>
    <div class="ks-workspace">
        <button type="button" class="ks-control ks-workspace__trigger" x-on:click="workspaceOpen = ! workspaceOpen" x-bind:aria-expanded="workspaceOpen" aria-haspopup="menu" aria-label="{{ __('shell.switch_workspace') }}">
            <span class="ks-workspace__mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($workspaceName ?? 'K', 0, 1)) }}</span>
            <span class="ks-workspace__identity"><strong>{{ $workspaceName ?? __('shell.workspace') }}</strong><small>{{ $workspaceType->label() }}</small></span>
            <x-filament::icon icon="heroicon-m-chevron-down" aria-hidden="true" />
        </button>

        <div class="ks-menu ks-workspace__menu" x-cloak x-show="workspaceOpen" x-transition.opacity x-on:click.outside="workspaceOpen = false" role="menu" aria-label="{{ __('shell.switch_workspace') }}">
            @foreach ([\App\Enums\WorkspaceType::Church, \App\Enums\WorkspaceType::Organization, \App\Enums\WorkspaceType::Central] as $type)
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

    @if ($websiteAction)
        <a class="ks-control ks-website" href="{{ $websiteAction['url'] }}" @if($websiteAction['external']) target="_blank" rel="noopener noreferrer" @endif>
            <x-filament::icon :icon="$websiteAction['label'] === __('shell.preview_website') ? 'heroicon-o-eye' : 'heroicon-o-arrow-top-right-on-square'" aria-hidden="true" />
            <span>{{ $websiteAction['label'] }}</span>
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
            @if ($websiteAction)
                <a class="ks-menu__item" href="{{ $websiteAction['url'] }}" @if($websiteAction['external']) target="_blank" rel="noopener noreferrer" @endif role="menuitem">
                    <span><strong>{{ $websiteAction['label'] }}</strong><small>{{ $websiteAction['description'] }}</small></span>
                    <x-filament::icon :icon="$websiteAction['label'] === __('shell.preview_website') ? 'heroicon-o-eye' : 'heroicon-o-arrow-top-right-on-square'" aria-hidden="true" />
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

    <div class="ks-account">
        <button
            type="button"
            class="ks-account__trigger"
            x-ref="accountTrigger"
            x-on:click="accountOpen = ! accountOpen"
            x-bind:aria-expanded="accountOpen"
            aria-haspopup="dialog"
            aria-controls="ks-account-panel"
            aria-label="{{ __('shell.open_account_panel') }}"
        >
            <span aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
        </button>

        <aside
            id="ks-account-panel"
            class="ks-account__panel"
            x-cloak
            x-show="accountOpen"
            x-transition.opacity
            x-on:click.outside="accountOpen = false"
            x-trap.inert.noscroll="accountOpen"
            role="dialog"
            aria-modal="true"
            aria-labelledby="ks-account-title"
        >
            <header class="ks-account__identity">
                <span class="ks-account__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                <span>
                    <strong id="ks-account-title">{{ auth()->user()->name }}</strong>
                    <small>{{ auth()->user()->email }}</small>
                </span>
                <button type="button" x-on:click="accountOpen = false; $nextTick(() => $refs.accountTrigger.focus())" aria-label="{{ __('shell.close_account_panel') }}">
                    <x-filament::icon icon="heroicon-o-x-mark" aria-hidden="true" />
                </button>
            </header>

            <section class="ks-account__active" aria-labelledby="ks-active-workspace">
                <p id="ks-active-workspace">{{ __('shell.active_workspace') }}</p>
                <div>
                    <span class="ks-workspace__mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($workspaceName ?? 'K', 0, 1)) }}</span>
                    <span><strong>{{ $workspaceName ?? __('shell.workspace') }}</strong><small>{{ $workspaceType->label() }}</small></span>
                </div>
            </section>

            <section class="ks-account__workspaces" aria-labelledby="ks-account-workspaces">
                <h3 id="ks-account-workspaces">{{ __('shell.your_workspaces') }}</h3>
                @foreach ([\App\Enums\WorkspaceType::Church, \App\Enums\WorkspaceType::Organization, \App\Enums\WorkspaceType::Central] as $type)
                    @if (($workspaces[$type->value] ?? collect())->isNotEmpty())
                        <p>{{ __('shell.workspace_group_'.$type->value) }}</p>
                        @foreach ($workspaces[$type->value] as $option)
                            <form method="POST" action="{{ route('workspace.switch', ['type' => $option->type->value, 'workspace' => $option->id]) }}">
                                @csrf
                                <button type="submit" @class(['ks-account__workspace', 'is-current' => $option->current]) @if($option->current) aria-current="page" @endif>
                                    <span><strong>{{ $option->name }}</strong><small>{{ $option->type->label() }}</small></span>
                                    @if ($option->current)
                                        <span class="ks-current">{{ __('shell.current') }}</span>
                                    @else
                                        <x-filament::icon icon="heroicon-o-arrow-right" aria-hidden="true" />
                                    @endif
                                </button>
                            </form>
                        @endforeach
                    @endif
                @endforeach
            </section>

            <nav class="ks-account__utilities" aria-label="{{ __('shell.account_utilities') }}">
                @if ($profileUrl)
                    <a href="{{ $profileUrl }}" wire:navigate><x-filament::icon icon="heroicon-o-user-circle" aria-hidden="true" /><span><strong>{{ __('shell.my_profile') }}</strong><small>{{ __('shell.profile_description') }}</small></span></a>
                @endif
                @if ($securityUrl)
                    <a href="{{ $securityUrl }}" wire:navigate><x-filament::icon icon="heroicon-o-shield-check" aria-hidden="true" /><span><strong>{{ __('shell.security') }}</strong><small>{{ __('shell.security_description') }}</small></span></a>
                @endif
                <a href="{{ $helpUrl }}"><x-filament::icon icon="heroicon-o-lifebuoy" aria-hidden="true" /><span><strong>{{ __('shell.help_support') }}</strong><small>{{ __('shell.help_description') }}</small></span></a>
            </nav>

            <form class="ks-account__logout" method="POST" action="{{ $logoutUrl }}">
                @csrf
                <button type="submit"><x-filament::icon icon="heroicon-o-arrow-left-start-on-rectangle" aria-hidden="true" />{{ __('shell.sign_out') }}</button>
            </form>
        </aside>
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
