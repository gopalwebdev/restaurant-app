<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\SuperAdminPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    // First, and it matters: both panels are served at /admin, and the
    // restaurant panel's sign-in route answers on any host. Registered before
    // it, the product team panel's root-domain routes are the ones the root
    // domain matches. PanelRoutingTest pins this.
    SuperAdminPanelProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
];
