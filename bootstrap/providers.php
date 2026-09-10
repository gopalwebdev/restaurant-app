<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\PlatformPanelProvider;
use App\Providers\Filament\RestaurantPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    // First, and it matters: both panels are served at /dashboard, and the
    // restaurant panel's sign-in route answers on any host. Registered before
    // it, the product team panel's root-domain routes are the ones the root
    // domain matches. PanelRoutingTest pins this.
    PlatformPanelProvider::class,
    RestaurantPanelProvider::class,
    HorizonServiceProvider::class,
];
