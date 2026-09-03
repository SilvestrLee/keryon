<?php

use App\Http\Controllers\ChurchActivationController;
use App\Http\Controllers\ChurchStaffInvitationController;
use App\Http\Controllers\InvitationLandingController;
use App\Http\Controllers\OrganizationCommunicationAssetController;
use App\Http\Controllers\PrivateMediaController;
use App\Http\Controllers\PublicMediaRenditionController;
use App\Http\Controllers\SelectLocaleController;
use App\Http\Controllers\SwitchWorkspaceController;
use App\Http\Controllers\WorkspaceSelectionController;
use App\Http\Middleware\SecureInvitationResponse;
use Illuminate\Support\Facades\Route;

Route::get('/media/{rendition}', PublicMediaRenditionController::class)
    ->whereUuid('rendition')
    ->name('media.public');

Route::get('/app/media/{asset}', PrivateMediaController::class)
    ->whereUuid('asset')
    ->name('media.private');

Route::get('/app/organization-assets/{asset}', OrganizationCommunicationAssetController::class)
    ->whereUuid('asset')
    ->name('organization-communications.assets.private');

Route::middleware([SecureInvitationResponse::class])->group(function (): void {
    Route::get('/invitation/continue/{continuation}', [InvitationLandingController::class, 'show'])->name('invitations.continue');
    Route::post('/invitation/continue/{continuation}/identity', [InvitationLandingController::class, 'establish'])->name('invitations.establish');
    Route::get('/invitation/continue/{continuation}/review', [InvitationLandingController::class, 'review'])->middleware('auth')->name('invitations.review');
    Route::post('/invitation/continue/{continuation}/accept', [InvitationLandingController::class, 'accept'])->middleware('auth')->name('invitations.accept');
    Route::get('/invitation/{type}/{token}', [InvitationLandingController::class, 'land'])->whereIn('type', ['activation', 'staff'])->name('invitations.landing');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/workspaces', WorkspaceSelectionController::class)->name('workspaces.select');
    Route::post('/workspace/switch/{type}/{workspace}', SwitchWorkspaceController::class)
        ->whereIn('type', ['church', 'organization', 'central'])
        ->whereNumber('workspace')
        ->name('workspace.switch');
    Route::post('/account/locale', SelectLocaleController::class)->name('account.locale');
    Route::get('/church-activation/{token}', [ChurchActivationController::class, 'show'])->name('church-activation.show');
    Route::post('/church-activation/{token}', [ChurchActivationController::class, 'accept'])->name('church-activation.accept');
    Route::get('/church-staff-invitation/accepted/{invitation}', [ChurchStaffInvitationController::class, 'accepted'])->name('church-staff-invitations.accepted');
    Route::post('/church-staff-invitation/accepted/{invitation}/enter', [ChurchStaffInvitationController::class, 'enter'])->name('church-staff-invitations.enter');
    Route::get('/church-staff-invitation/{token}', [ChurchStaffInvitationController::class, 'show'])->name('church-staff-invitations.show');
    Route::post('/church-staff-invitation/{token}', [ChurchStaffInvitationController::class, 'accept'])->name('church-staff-invitations.accept');
});

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

Route::get('/pricing', function () {
    return view('site.pricing');
})->name('site.pricing');

foreach ([
    'resources' => 'Resources',
    'about' => 'About',
] as $slug => $pageTitle) {
    Route::get("/{$slug}", function () use ($pageTitle) {
        return view('site.coming-soon', ['pageTitle' => $pageTitle]);
    })->name("site.{$slug}");
}
