<?php

use Illuminate\Support\Facades\Route;

// Subdomain routes first: an unconstrained route would otherwise swallow them.
require __DIR__.'/tenant.php';

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
