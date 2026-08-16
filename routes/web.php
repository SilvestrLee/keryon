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

foreach ([
    'solutions' => 'Solutions',
    'pricing' => 'Pricing',
    'resources' => 'Resources',
    'about' => 'About',
] as $slug => $pageTitle) {
    Route::get("/{$slug}", function () use ($pageTitle) {
        return view('site.coming-soon', ['pageTitle' => $pageTitle]);
    })->name("site.{$slug}");
}
