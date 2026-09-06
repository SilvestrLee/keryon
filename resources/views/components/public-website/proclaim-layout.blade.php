@props(['church', 'brand', 'logo', 'mark', 'serviceTimes', 'socialLinks', 'palette', 'page', 'title', 'description', 'seo', 'preview' => false, 'navigation' => []])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <meta name="robots" content="{{ $preview ? 'noindex, nofollow' : 'index, follow' }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    @if ($mark)
        <meta property="og:image" content="{{ $mark['url'] }}">
        {{-- K-WEB-V1-001C-B — the approved v1 favicon contract: the
        Church's existing Brand mark, reused as-is (§2/§11 of the
        directive). $mark is already resolved by the theme-independent
        PublicWebsiteContent seam into a public rendition URL (never a
        private Media route) for both preview and published rendering,
        and is already rights-gated/immutable-per-publication via the
        exact same mechanism that already protects the mark for
        Open Graph above — no favicon-specific plumbing was added. No
        fallback to the primary logo: absent a mark, no custom icon tag
        is rendered at all. --}}
        <link rel="icon" href="{{ $mark['url'] }}">
    @endif
    <script type="application/ld+json">{!! json_encode($seo['organization'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @vite(['resources/css/public-website.css', 'resources/js/app.js'])
</head>
<body
    class="proclaim"
    style="--church-accent: {{ $palette['accent'] }}; --church-heading: {{ $palette['heading'] }}; --church-body: {{ $palette['body'] }}"
    x-data="{ menuOpen: false }"
    @keydown.escape.window="menuOpen = false"
>
    <a class="pw-skip-link" href="#main-content">Skip to content</a>

    <header class="pw-header">
        <div class="pw-shell pw-nav-shell">
            <a class="pw-brand" href="{{ $preview ? route('website.preview') : '/' }}" aria-label="{{ $church->name }} home">
                @if ($logo)
                    <img src="{{ $logo['url'] }}" alt="{{ $logo['alt'] }}" width="{{ $logo['width'] ?: 240 }}" height="{{ $logo['height'] ?: 72 }}">
                @else
                    <span>{{ $church->name }}</span>
                @endif
            </a>

            <nav class="pw-desktop-nav" aria-label="Primary navigation">
                {{-- K-WEB-V1-001D-B §40 — desktop, mobile, and footer all
                iterate this single resolved `$navigation` list (built once
                in ProclaimTheme from the effective, theme-supported page
                configuration) rather than each maintaining its own
                independent page-identity array. --}}
                @foreach ($navigation as $item)
                    <a href="{{ $preview ? route('website.preview', ['page' => $item['key'] === 'home' ? null : $item['key']]) : ($item['key'] === 'home' ? '/' : '/'.$item['key']) }}" @class(['is-current' => $page === $item['key']]) @if($page === $item['key']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
            </nav>

            <button class="pw-menu-button" type="button" @click="menuOpen = ! menuOpen" :aria-expanded="menuOpen.toString()" aria-controls="mobile-navigation">
                <span class="sr-only" x-text="menuOpen ? 'Close navigation' : 'Open navigation'">Open navigation</span>
                <span aria-hidden="true" x-text="menuOpen ? 'Close' : 'Menu'">Menu</span>
            </button>
        </div>

        <nav id="mobile-navigation" class="pw-mobile-nav" x-cloak x-show="menuOpen" x-transition.opacity.duration.200ms aria-label="Mobile navigation">
            <div class="pw-shell">
                @foreach ($navigation as $item)
                    <a href="{{ $preview ? route('website.preview', ['page' => $item['key'] === 'home' ? null : $item['key']]) : ($item['key'] === 'home' ? '/' : '/'.$item['key']) }}" @class(['is-current' => $page === $item['key']]) @if($page === $item['key']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
            </div>
        </nav>
    </header>

    <main id="main-content">
        {{ $slot }}
    </main>

    <footer class="pw-footer">
        <div class="pw-shell pw-footer-grid">
            <div class="pw-footer-identity">
                @if ($logo)
                    <img src="{{ $logo['url'] }}" alt="{{ $logo['alt'] }}" width="{{ $logo['width'] ?: 240 }}" height="{{ $logo['height'] ?: 72 }}" loading="lazy">
                @else
                    <p class="pw-footer-name">{{ $church->name }}</p>
                @endif
                @if ($church->address)
                    <p>{{ $church->address }}</p>
                @endif
                @if ($church->phone)
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $church->phone) }}">{{ $church->phone }}</a>
                @endif
                @if ($church->email)
                    <a href="mailto:{{ $church->email }}">{{ $church->email }}</a>
                @endif
            </div>

            @if ($serviceTimes->isNotEmpty())
                <div>
                    <h2>Gather with us</h2>
                    <ul>
                        @foreach ($serviceTimes as $service)
                            <li><strong>{{ $service->label }}</strong><span>{{ $service->day_of_week?->label() }} {{ $service->time }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <h2>Explore</h2>
                <nav aria-label="Footer navigation">
                    {{-- K-WEB-V1-001D-B §41 — the footer deliberately
                    excludes Home (an intentional presentation rule over
                    the same canonical `$navigation` source, not a second
                    independent page-identity list). --}}
                    @foreach (array_filter($navigation, fn ($item) => $item['key'] !== 'home') as $item)
                        <a href="{{ $preview ? route('website.preview', ['page' => $item['key']]) : '/'.$item['key'] }}">{{ $item['label'] }}</a>
                    @endforeach
                </nav>
            </div>
        </div>

        @if ($socialLinks->isNotEmpty())
            <div class="pw-shell pw-socials" aria-label="Social links">
                @foreach ($socialLinks as $social)
                    <a href="{{ $social->publicUrl }}" rel="noopener noreferrer">{{ $social->platform->label() }}</a>
                @endforeach
            </div>
        @endif
    </footer>
</body>
</html>
