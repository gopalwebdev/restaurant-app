<?php

use App\Http\Controllers\Guest\HomeController;
use App\Http\Controllers\Guest\MenuController;
use App\Http\Controllers\Guest\ProgressiveWebAppController;
use App\Http\Controllers\Guest\TileController;
use App\Http\Controllers\Preferences\UpdateLanguageController;
use App\Http\Middleware\HandleGuestAppRequests;
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
| The guest app is the one Inertia app on a subdomain. Its own root template
| loads its own entry and its own page chunk, and never any of Filament, which
| the restaurant's panel serves from its own compiled assets.
|
*/

Route::domain('{restaurant}.'.config('app.domain'))->group(function (): void {
    /*
     * The guest app: what a diner reads at the table, reached by QR code, and
     * installable so a guest who comes back keeps it on their home screen.
     *
     * A guest lands on the tiles the restaurant arranged, and walks from there
     * into a menu or a PDF.
     */
    Route::name('guest.')->middleware(HandleGuestAppRequests::class)->group(function (): void {
        Route::get('/', HomeController::class)->name('home');

        Route::get('menus/{menu}', MenuController::class)->name('menus.show');

        // A tile's own page exists only for the ones that open a PDF: the file
        // is embedded there so the app keeps its header and its back arrow.
        Route::get('tiles/{tile}', [TileController::class, 'show'])->name('tiles.show');
    });

    /*
     * A tile's picture and its PDF. Served outside the Inertia middleware
     * because neither is a page, and out of the private disk rather than a
     * public link so both stay checked against the restaurant in the domain.
     */
    Route::name('guest.tiles.')->group(function (): void {
        Route::get('tiles/{tile}/image', [TileController::class, 'image'])->name('image.show');
        Route::get('tiles/{tile}/document', [TileController::class, 'document'])->name('document.show');
    });

    /*
     * What makes the guest app installable. At the root of the subdomain so the
     * worker's scope is the whole app, and outside the Inertia middleware: a
     * manifest is JSON and a service worker is JavaScript, and neither is a page.
     */
    Route::name('guest.')->group(function (): void {
        Route::get('manifest.webmanifest', [ProgressiveWebAppController::class, 'manifest'])->name('manifest');
        Route::get('service-worker.js', [ProgressiveWebAppController::class, 'serviceWorker'])->name('service-worker');
    });

    /*
     * Switching language, from the guest app and from the restaurant's panel.
     * It renders nothing: it records the choice and sends the visitor back to
     * the page they were on, which is then re-rendered in that language.
     */
    Route::put('preferences/language', UpdateLanguageController::class)
        ->name('preferences.language.update');
});
