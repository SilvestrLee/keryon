<?php

use App\Http\Controllers\PublicWebsiteController;
use App\Http\Controllers\WebsitePreviewController;
use App\Http\Middleware\AuthorizeWebsitePreview;
use App\Http\Middleware\ResolvePublicWebsite;
use Illuminate\Support\Facades\Route;

Route::get('/admin/website/preview/{page?}', WebsitePreviewController::class)
    ->where('page', 'home|about|leadership|ministries|contact')
    ->middleware(AuthorizeWebsitePreview::class)
    ->name('website.preview');

Route::domain('{church}.'.config('public-website.base_domain'))
    ->where(['church' => '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?'])
    ->middleware(ResolvePublicWebsite::class)
    ->name('church-website.')
    ->group(function (): void {
        Route::get('/', [PublicWebsiteController::class, 'home'])->name('home');
        Route::get('/about', [PublicWebsiteController::class, 'about'])->name('about');
        Route::get('/leadership', [PublicWebsiteController::class, 'leadership'])->name('leadership');
        Route::get('/ministries', [PublicWebsiteController::class, 'ministries'])->name('ministries');
        Route::get('/contact', [PublicWebsiteController::class, 'contact'])->name('contact');
        Route::get('/sitemap.xml', [PublicWebsiteController::class, 'sitemap'])->name('sitemap');
        Route::get('/robots.txt', [PublicWebsiteController::class, 'robots'])->name('robots');
    });

// A malformed single-label subdomain beneath Keryon's first-party public
// Website base must not fall through to the host-agnostic marketing site.
Route::domain('{invalidPublicHost}')
    ->where([
        'invalidPublicHost' => '.+\\.'.preg_quote((string) config('public-website.base_domain'), '/'),
    ])
    ->group(function (): void {
        Route::fallback(fn () => abort(404));
    });
