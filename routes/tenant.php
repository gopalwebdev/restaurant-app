<?php

use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Storefront Routes
|--------------------------------------------------------------------------
|
| Every route here is served from a restaurant's own subdomain, so the
| {restaurant} parameter resolves by slug into the tenant for the request.
| These are registered before the root-domain routes so that a subdomain
| never falls through to the marketing site.
|
*/

Route::domain('{restaurant}.'.config('app.domain'))->group(function (): void {
    Route::get('/', StorefrontController::class)->name('storefront');
});
