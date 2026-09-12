<?php

use App\Enums\WebsitePageType;
use App\Http\Controllers\PublicWebsiteController;
use App\Http\Controllers\WebsitePreviewController;
use App\Http\Middleware\AuthorizeWebsitePreview;
use App\Http\Middleware\ResolvePublicWebsite;
use Illuminate\Support\Facades\Route;

// K-WEB-V1-001D-B §22 — the allow-list is derived from the canonical
// registry, not hand-maintained, so a future `WebsitePageType` case
// (K-WEB-V1-001D-C) is automatically permitted here without a route-file
// edit. `WebsitePreviewController` itself still independently validates
// via `WebsitePageType::tryFrom()` and theme support — this regex is
// only the routing layer's own first, cheap filter.
$previewPagePattern = implode('|', array_map(fn (WebsitePageType $type): string => $type->value, WebsitePageType::cases()));

Route::get('/admin/website/preview/{page?}', WebsitePreviewController::class)
    ->where('page', $previewPagePattern)
    ->middleware(AuthorizeWebsitePreview::class)
    ->name('website.preview');

// K-WEB-V1-001D-B §18/§19 — five route *definitions* remain, with their
// exact existing URLs and names unchanged (nothing outside this file
// needs to change), but each now binds to the single registry-driven
// `PublicWebsiteController::render()` dispatch via a route default
// rather than its own dedicated one-line controller method.
$websiteRoutes = function (): void {
    Route::get('/', [PublicWebsiteController::class, 'render'])->defaults('page', WebsitePageType::Home->value)->name('home');
    Route::get('/about', [PublicWebsiteController::class, 'render'])->defaults('page', WebsitePageType::About->value)->name('about');
    Route::get('/leadership', [PublicWebsiteController::class, 'render'])->defaults('page', WebsitePageType::Leadership->value)->name('leadership');
    Route::get('/ministries', [PublicWebsiteController::class, 'render'])->defaults('page', WebsitePageType::Ministries->value)->name('ministries');
    // K-WEB-V1-001D-C §12/§22/§58 — four new stable v1 slugs, extended
    // through the exact same registry-driven dispatch, no detail routes.
    Route::get('/events', [PublicWebsiteController::class, 'render'])->defaults('page', WebsitePageType::Events->value)->name('events');
    Route::get('/messages', [PublicWebsiteController::class, 'render'])->defaults('page', WebsitePageType::Messages->value)->name('messages');
    Route::get('/publications', [PublicWebsiteController::class, 'render'])->defaults('page', WebsitePageType::Publications->value)->name('publications');
    Route::get('/giving', [PublicWebsiteController::class, 'render'])->defaults('page', WebsitePageType::Giving->value)->name('giving');
    Route::get('/contact', [PublicWebsiteController::class, 'render'])->defaults('page', WebsitePageType::Contact->value)->name('contact');
    Route::get('/sitemap.xml', [PublicWebsiteController::class, 'sitemap'])->name('sitemap');
    Route::get('/robots.txt', [PublicWebsiteController::class, 'robots'])->name('robots');
};

// K-WEB-P2-ROBOTS-001B §5/§8 — the platform-wide static `public/robots.txt`
// scaffold shadowed every host's robots authority before Laravel's router
// ever ran (see K-WEB-P2-ROBOTS-001A). It has been removed; the routes
// below give Keryon's own hosts an intentional, host-aware crawler policy.
// This is guidance only, not an access boundary — see
// `RejectUnsupportedKeryonHost` and each panel's own auth middleware for
// the actual security boundary on `app.keryon.app` / `central.keryon.app`.
$platformRobots = fn (string $body) => response($body)->header('Content-Type', 'text/plain; charset=UTF-8');

Route::domain(config('public-website.base_domain'))->group(function () use ($platformRobots): void {
    Route::get('/', fn () => view('site.home'))->name('platform-marketing.home');
    Route::get('/about', fn () => view('site.coming-soon', ['pageTitle' => 'About']))->name('platform-marketing.about');
    // The marketing domain is not a Church tenant, so it gets its own
    // minimal, always-allow policy rather than the Church-aware
    // `$websiteRoutes` closure below. No `Sitemap:` line until a
    // marketing sitemap actually exists (§8 — do not invent one here).
    Route::get('/robots.txt', fn () => $platformRobots("User-agent: *\nAllow: /\n"))->name('platform-marketing.robots');
});

// §9/§10 — these exact-host declarations must be registered before the
// `{church}.` wildcard group below, because a bare label such as "app" or
// "central" would otherwise also satisfy that group's `{church}` pattern
// and be handed to Church-slug resolution instead.
foreach (config('public-website.application_hosts', []) as $applicationHost) {
    Route::domain($applicationHost)
        ->get('/robots.txt', fn () => $platformRobots("User-agent: *\nDisallow: /\n"));
}

if (filled(config('central.domain'))) {
    // K-WEB-P2-ROBOTS-001C — `RejectUnsupportedKeryonHost` admits only
    // `filament.central.*` on this host; this route needs its own exact,
    // stable name so that guard can admit it by name without opening the
    // Central host to routes generally.
    Route::domain(config('central.domain'))
        ->get('/robots.txt', fn () => $platformRobots("User-agent: *\nDisallow: /\n"))
        ->name('platform-central.robots');
}

Route::domain('{church}.'.config('public-website.base_domain'))
    ->where(['church' => '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?'])
    ->middleware(ResolvePublicWebsite::class)
    ->name('church-website.')
    ->group($websiteRoutes);

$baseDomain = preg_quote((string) config('public-website.base_domain'), '/');
Route::domain('{customHost}')
    ->where(['customHost' => "(?!(?:{$baseDomain}|.+\\.{$baseDomain}|localhost|127\\.0\\.0\\.1)$)[a-z0-9.-]+"])
    ->middleware(ResolvePublicWebsite::class)
    ->name('custom-church-website.')
    ->group($websiteRoutes);

// A malformed single-label subdomain beneath Keryon's first-party public
// Website base must not fall through to the host-agnostic marketing site.
Route::domain('{invalidPublicHost}')
    ->where([
        'invalidPublicHost' => '.+\\.'.preg_quote((string) config('public-website.base_domain'), '/'),
    ])
    ->group(function (): void {
        Route::fallback(fn () => abort(404));
    });
