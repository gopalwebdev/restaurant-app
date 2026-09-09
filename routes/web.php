<?php

use App\Http\Controllers\Preferences\UpdateLanguageController;
use Illuminate\Support\Facades\Route;

// Subdomain routes first: an unconstrained route would otherwise swallow them.
require __DIR__.'/tenant.php';

Route::inertia('/', 'welcome')->name('home');

/*
 * Switching language from the product team's panel, which is the one surface
 * not served from a restaurant's subdomain. Same controller as the tenant
 * route in tenant.php — it needs its own registration only because a form must
 * post to the host it was rendered on, or the session cookie does not travel.
 */
Route::put('preferences/language', UpdateLanguageController::class)
    ->name('panel.language.update');
