<?php

use App\Http\Controllers\Guest\MenuController;
use App\Http\Controllers\Staff\HomeController;
use App\Http\Controllers\Staff\ProgressiveWebAppController;
use App\Http\Controllers\Staff\SignInController;
use App\Http\Middleware\EnsureStaffMemberWorksHere;
use App\Http\Middleware\HandleGuestAppRequests;
use App\Http\Middleware\HandleStaffAppRequests;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Everything here is served from a restaurant's own subdomain, so the
| {restaurant} parameter resolves by slug into the tenant for the request.
| These are registered before the root-domain routes so a subdomain never
| falls through to the marketing site.
|
| Two apps live here and they share nothing but the domain. Each has its own
| Inertia root template, so each loads its own entry and its own page chunk
| and neither downloads the other — or any of Filament, which the panels
| serve from their own compiled assets.
|
*/

Route::domain('{restaurant}.'.config('app.domain'))->group(function (): void {
    /*
     * The guest app: the menu a diner reads at the table, reached by QR code.
     * No sign-in and no install prompt — see .ai/rules/js.md.
     */
    Route::middleware(HandleGuestAppRequests::class)->group(function (): void {
        Route::get('/', MenuController::class)->name('storefront');
    });

    /*
     * The staff app: installed once and kept, so it carries a manifest and a
     * service worker. `/staff/login` is the way in; the two endpoints behind it
     * are named for what they create rather than for the act of signing in.
     */
    Route::prefix('staff')->name('staff.')->middleware(HandleStaffAppRequests::class)->group(function (): void {
        Route::get('login', [SignInController::class, 'create'])->name('login');
        Route::post('sign-in-codes', [SignInController::class, 'storeCode'])->name('sign-in-codes.store');
        Route::post('session', [SignInController::class, 'store'])->name('session.store');
        Route::delete('session', [SignInController::class, 'destroy'])->name('session.destroy');

        // Signed in is not enough: an account is platform-wide, so this also
        // checks they work at the restaurant whose subdomain they are on.
        Route::middleware(['auth', EnsureStaffMemberWorksHere::class])->group(function (): void {
            Route::get('/', HomeController::class)->name('home');
        });
    });

    /*
     * Served outside the Inertia middleware: a manifest is JSON and a service
     * worker is JavaScript, and neither is a page.
     */
    Route::get('staff/manifest.webmanifest', [ProgressiveWebAppController::class, 'manifest'])->name('staff.manifest');
    Route::get('staff/service-worker.js', [ProgressiveWebAppController::class, 'serviceWorker'])->name('staff.service-worker');
});
