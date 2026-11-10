<?php

use App\Http\Controllers\Admin\AgencyGoogleController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ClientAccessController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ClientLinkController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\GoogleCallbackController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Middleware\ResolveOnboardingLink;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.dashboard'));

/*
|--------------------------------------------------------------------------
| FTS staff authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Google OAuth redirect URI (one URI for agency and client flows)
|--------------------------------------------------------------------------
*/
Route::get('/google/callback', GoogleCallbackController::class)
    ->middleware('throttle:onboarding')
    ->name('google.callback');

/*
|--------------------------------------------------------------------------
| Client onboarding (no FTS login; the secret token in the URL is the credential)
|--------------------------------------------------------------------------
*/
Route::prefix('gbp/connect/{token}')
    ->middleware(['throttle:onboarding', ResolveOnboardingLink::class])
    ->name('onboarding.')
    ->controller(OnboardingController::class)
    ->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('/google', 'start')->name('start');
        Route::get('/profiles', 'profiles')->name('profiles');
        Route::post('/profiles', 'select')->name('select');
        Route::get('/confirm', 'confirm')->name('confirm');
        Route::post('/confirm', 'authorizeRequest')->name('authorize');
    });

/*
|--------------------------------------------------------------------------
| FTS admin dashboard
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth', 'can:manage-gbp'])->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');

    Route::post('clients/{client}/link', [ClientLinkController::class, 'store'])->name('clients.link.store');
    Route::delete('clients/{client}/link', [ClientLinkController::class, 'destroy'])->name('clients.link.destroy');
    Route::post('clients/{client}/link/email', [ClientLinkController::class, 'email'])->name('clients.link.email');

    Route::post('clients/{client}/refresh', [ClientAccessController::class, 'refresh'])->name('clients.refresh');
    Route::post('clients/{client}/retry', [ClientAccessController::class, 'retry'])->name('clients.retry');
    Route::post('clients/{client}/method', [ClientAccessController::class, 'changeMethod'])->name('clients.method');
    Route::post('clients/{client}/disconnect-google', [ClientAccessController::class, 'disconnectGoogle'])->name('clients.disconnect');
    Route::post('clients/{client}/manual/submitted', [ClientAccessController::class, 'manualSubmitted'])->name('clients.manual.submitted');
    Route::post('clients/{client}/manual/connected', [ClientAccessController::class, 'manualConnected'])->name('clients.manual.connected');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/test', [SettingsController::class, 'test'])->name('settings.test');
    Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('invitations/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

    Route::get('settings/google/connect', [AgencyGoogleController::class, 'connect'])->name('settings.google.connect');
    Route::post('settings/google/disconnect', [AgencyGoogleController::class, 'disconnect'])->name('settings.google.disconnect');
});
