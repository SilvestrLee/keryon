<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('site.home');
})->name('home');

Route::get('/book-demo', function () {
    return view('site.book-demo');
})->name('site.book-demo');

Route::get('/features', function () {
    return view('site.features');
})->name('site.features');

// K-WEB-003: public theme discovery only — these three routes render static
// marketing pages. THEME != CONTENT: theme selection must remain independent
// of church content persistence; no activation backend, theme table, or
// content-duplication-on-activation exists yet. See docs/06-Engineering/
// Website_Content_Contract.md for the church-content side of this contract.
Route::get('/themes', function () {
    return view('site.themes');
})->name('site.themes');

Route::get('/themes/proclaim', function () {
    return view('site.themes.proclaim');
})->name('site.themes.proclaim');

Route::get('/themes/custom-design', function () {
    return view('site.themes.custom-design');
})->name('site.themes.custom-design');

Route::get('/solutions', function () {
    return view('site.solutions');
})->name('site.solutions');

foreach ([
    'pricing' => 'Pricing',
    'resources' => 'Resources',
    'about' => 'About',
] as $slug => $pageTitle) {
    Route::get("/{$slug}", function () use ($pageTitle) {
        return view('site.coming-soon', ['pageTitle' => $pageTitle]);
    })->name("site.{$slug}");
}
